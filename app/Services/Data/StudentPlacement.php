<?php

namespace Crater\Services\Data;

use Crater\Models\AcademicYear;
use Crater\Models\AuditLog;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\GradeLevel;
use Crater\Models\SchoolLevel;
use Crater\Models\Student;
use Crater\Models\User;
use Crater\Services\Audit\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fila 7: la ubicacion academica de un alumno es su matricula vigente.
 *
 * - Alta: abre la primera matricula.
 * - Correccion de carga: el alumno estaba mal cargado; se corrige la matricula
 *   vigente en el lugar, sin historial academico.
 * - Pase: cambio real de curso, division o nivel; la matricula vigente se
 *   cierra como transferred_out y se abre otra, enlazada a la anterior.
 *
 * Los campos legacy del alumno (level, grade, division, school_year) solo se
 * escriben aca, derivados de la matricula vigente.
 */
class StudentPlacement
{
    const MODE_CORRECTION = 'correction';
    const MODE_TRANSFER = 'transfer';

    const LEVEL_LABELS = ['primary' => 'Primario', 'secondary' => 'Secundario', 'tertiary' => 'Terciario'];

    /** Resuelve y valida nivel, ciclo, curso y division como una ubicacion coherente. */
    public function destination(int $companyId, array $data): array
    {
        $level = SchoolLevel::where('company_id', $companyId)->where('enabled', true)->find($data['school_level_id']);
        $year = $level ? AcademicYear::acrossLevels()->where('company_id', $companyId)
            ->where('school_level_id', $level->id)->find($data['academic_year_id']) : null;
        $grade = $level ? GradeLevel::acrossLevels()->where('company_id', $companyId)
            ->where('school_level_id', $level->id)->where('enabled', true)->find($data['grade_level_id']) : null;
        $division = ($year && $grade) ? Division::acrossLevels()->where('company_id', $companyId)
            ->where('school_level_id', $level->id)->where('academic_year_id', $year->id)
            ->where('grade_level_id', $grade->id)->where('enabled', true)->whereKey($data['division_id'])->first() : null;
        if (! $division) {
            throw ValidationException::withMessages([
                'division_id' => ['El nivel, ciclo, curso y división elegidos no forman una ubicación académica válida.'],
            ]);
        }

        return [$level, $year, $grade, $division];
    }

    /** Primera matricula del alumno (alta). */
    public function place(Student $student, Division $division, AcademicYear $year, ?string $on = null): Enrollment
    {
        return DB::transaction(function () use ($student, $division, $year, $on) {
            // Mismo orden de bloqueo que relocate: division y alumno.
            $division = Division::acrossLevels()->whereKey($division->id)->lockForUpdate()->firstOrFail();
            Student::acrossLevels()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_if($this->activeEnrollment($student, true), 409, 'El alumno ya tiene una matrícula vigente: usá Reubicar.');
            $this->ensureCapacity($division, null);
            $enrollment = Enrollment::create($this->enrollmentData($student, $division, $year) + [
                'enrolled_on' => $on ?? now()->toDateString(), 'attendance_condition' => 'regular', 'has_curricular_adaptation' => false,
            ]);
            $this->syncLegacy($student);

            return $enrollment;
        });
    }

    /**
     * Correccion o pase. Devuelve [alumno, matricula vigente].
     *
     * @param  array{school_level_id:int, academic_year_id:int, grade_level_id:int, division_id:int, reason:string, mode:string, effective_on?:string}  $data
     */
    public function relocate(User $actor, Student $student, int $companyId, array $data): array
    {
        return DB::transaction(function () use ($actor, $student, $companyId, $data) {
            [$level, $year, $grade, $division] = $this->destination($companyId, $data);
            $division = Division::acrossLevels()->whereKey($division->id)->lockForUpdate()->firstOrFail();
            $student = Student::acrossLevels()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $current = $this->activeEnrollment($student, true);
            $origin = $this->describe($current, $student);

            if ($data['mode'] === self::MODE_TRANSFER) {
                if (! $current) {
                    throw ValidationException::withMessages(['mode' => ['El alumno no tiene una matrícula vigente: para ubicarlo usá Corrección de carga.']]);
                }
                if ((int) $current->division_id === (int) $division->id) {
                    throw ValidationException::withMessages(['division_id' => ['El pase tiene que ser a otra división.']]);
                }
                $on = $data['effective_on'] ?? now()->toDateString();
                if ($on < optional($current->enrolled_on)->toDateString()) {
                    throw ValidationException::withMessages(['effective_on' => ['El pase no puede ser anterior al inicio de la matrícula vigente.']]);
                }
                $this->ensureCapacity($division, null);
                $current->update(['status' => Enrollment::STATUS_TRANSFERRED_OUT, 'left_on' => $on]);
                $enrollment = Enrollment::create($this->enrollmentData($student, $division, $year) + [
                    'enrolled_on' => $on, 'attendance_condition' => $current->attendance_condition ?? 'regular',
                    'has_curricular_adaptation' => (bool) $current->has_curricular_adaptation, 'previous_enrollment_id' => $current->id,
                ]);
            } else {
                if ($current && (int) $current->division_id !== (int) $division->id && $current->sectionEnrollments()->exists()) {
                    throw ValidationException::withMessages(['division_id' => ['Esta matrícula ya tiene actividad académica: para moverla usá Pase, que conserva el historial.']]);
                }
                $this->ensureCapacity($division, $current);
                if ($current) {
                    $current->update($this->enrollmentData($student, $division, $year));
                    $enrollment = $current->fresh();
                } else {
                    $enrollment = Enrollment::create($this->enrollmentData($student, $division, $year) + [
                        'enrolled_on' => now()->toDateString(), 'attendance_condition' => 'regular', 'has_curricular_adaptation' => false,
                    ]);
                }
            }
            $this->syncLegacy($student);

            app(Auditor::class)->record('student_relocated', $actor,
                ['type' => Student::class, 'id' => $student->id, 'company_id' => $companyId, 'school_level_id' => $level->id],
                $origin, [
                    'school_level_id' => (int) $level->id, 'academic_year_id' => (int) $year->id,
                    'grade_level_id' => (int) $grade->id, 'division_id' => (int) $division->id,
                    'enrollment_id' => (int) $enrollment->id, 'mode' => $data['mode'], 'reason' => $data['reason'],
                    'effective_on' => $data['mode'] === self::MODE_TRANSFER ? ($data['effective_on'] ?? now()->toDateString()) : null,
                ], AuditLog::SEVERITY_HIGH);

            return [$student->fresh(), $enrollment];
        });
    }

    /** Historial completo de matriculas del alumno, en todos los niveles de la empresa. */
    public function history(Student $student)
    {
        return Enrollment::acrossLevels()->where('company_id', $student->company_id)->where('student_id', $student->id)
            ->with(['division' => fn ($q) => $q->withoutGlobalScope('school_level')->select('id', 'name', 'grade_level_id', 'school_level_id'),
                'division.gradeLevel' => fn ($q) => $q->withoutGlobalScope('school_level')->select('id', 'name'),
                'academicYear' => fn ($q) => $q->withoutGlobalScope('school_level')->select('id', 'year', 'name')])
            ->orderBy('enrolled_on')->orderBy('id')->get()
            ->map(function (Enrollment $enrollment) {
                $level = SchoolLevel::find($enrollment->school_level_id);

                return [
                    'id' => $enrollment->id, 'status' => $enrollment->status,
                    'school_level' => $level ? (self::LEVEL_LABELS[$level->code] ?? $level->name) : null,
                    'school_level_id' => $enrollment->school_level_id,
                    'academic_year' => optional($enrollment->academicYear)->year,
                    'grade' => optional(optional($enrollment->division)->gradeLevel)->name,
                    'division' => optional($enrollment->division)->name, 'division_id' => $enrollment->division_id,
                    'enrolled_on' => optional($enrollment->enrolled_on)->toDateString(),
                    'left_on' => optional($enrollment->left_on)->toDateString(),
                    'previous_enrollment_id' => $enrollment->previous_enrollment_id,
                ];
            })->values();
    }

    /** Campos legacy derivados de la matricula vigente; sin matricula no se tocan. */
    public function syncLegacy(Student $student): void
    {
        $current = $this->activeEnrollment($student, false);
        if (! $current) {
            return;
        }
        $division = Division::acrossLevels()->with(['gradeLevel' => fn ($q) => $q->withoutGlobalScope('school_level')])->find($current->division_id);
        $year = AcademicYear::acrossLevels()->find($current->academic_year_id);
        $level = SchoolLevel::find($current->school_level_id);
        Student::acrossLevels()->whereKey($student->id)->update([
            'school_level_id' => $current->school_level_id,
            'level' => $level ? (self::LEVEL_LABELS[$level->code] ?? $level->name) : null,
            'grade' => optional(optional($division)->gradeLevel)->name, 'division' => optional($division)->name,
            'school_year' => optional($year)->year, 'updated_at' => now(),
        ]);
    }

    public function activeEnrollment(Student $student, bool $lock): ?Enrollment
    {
        $query = Enrollment::acrossLevels()->where('company_id', $student->company_id)->where('student_id', $student->id)
            ->where('status', Enrollment::STATUS_ACTIVE)->orderByDesc('enrolled_on')->orderByDesc('id');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    protected function ensureCapacity(Division $division, ?Enrollment $ignore): void
    {
        if (! $division->capacity) {
            return;
        }
        $occupied = Enrollment::acrossLevels()->where('division_id', $division->id)->where('status', Enrollment::STATUS_ACTIVE)
            ->when($ignore, fn ($q) => $q->where('id', '<>', $ignore->id))->count();
        if ($occupied >= $division->capacity) {
            throw ValidationException::withMessages(['division_id' => ['No quedan lugares en la división elegida.']]);
        }
    }

    protected function enrollmentData(Student $student, Division $division, AcademicYear $year): array
    {
        return ['company_id' => $student->company_id, 'school_level_id' => $division->school_level_id,
            'academic_year_id' => $year->id, 'student_id' => $student->id, 'division_id' => $division->id,
            'status' => Enrollment::STATUS_ACTIVE, 'left_on' => null];
    }

    protected function describe(?Enrollment $current, Student $student): array
    {
        $grade = $current ? Division::acrossLevels()->whereKey($current->division_id)->value('grade_level_id') : null;

        return [
            'school_level_id' => $current ? (int) $current->school_level_id : ($student->school_level_id ? (int) $student->school_level_id : null),
            'academic_year_id' => $current ? (int) $current->academic_year_id : null,
            'grade_level_id' => $grade ? (int) $grade : null,
            'division_id' => $current ? (int) $current->division_id : null,
            'enrollment_id' => $current ? (int) $current->id : null,
        ];
    }
}
