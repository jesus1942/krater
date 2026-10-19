<?php

namespace Crater\Services\Attendance;

use Carbon\Carbon;
use Crater\Enums\Permission;
use Crater\Models\AttendanceRecord;
use Crater\Models\AuditLog;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\SchoolLevel;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Services\Audit\Auditor;
use Crater\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Toma de lista de alumnos. En los niveles de toma diaria la planilla es la
 * division del dia; en los de toma por materia, la seccion (materia del curso)
 * del dia. Quien toma lista lo hace dentro de su alcance: el preceptor en sus
 * divisiones, el docente en sus materias (y en Primario, en las divisiones
 * donde dicta), la direccion en todo el nivel.
 */
class StudentAttendance
{
    protected AccessManager $access;
    protected AttendanceRules $rules;

    public function __construct(AccessManager $access, AttendanceRules $rules)
    {
        $this->access = $access;
        $this->rules = $rules;
    }

    public function level(): SchoolLevel
    {
        $id = TenantContext::schoolLevelId();
        if (! $id) {
            throw ValidationException::withMessages(['school_level' => 'Elegí un nivel en la barra superior para tomar asistencia.']);
        }

        return SchoolLevel::where('company_id', TenantContext::companyId())->findOrFail($id);
    }

    /** Divisiones o secciones donde el usuario puede tomar lista hoy, segun el modo del nivel. */
    public function targets(User $user): array
    {
        $level = $this->level();
        $rules = $this->rules->forLevel($level);
        $divisions = Division::with(['gradeLevel:id,name,position'])->where('enabled', true)
            ->whereIn('academic_year_id', $this->openYears())->orderBy('grade_level_id')->orderBy('name')
            ->get(['id', 'name', 'grade_level_id', 'academic_year_id']);
        $label = fn ($d) => trim(optional($d->gradeLevel)->name.' '.$d->name);
        if ($rules['mode'] === AttendanceRules::DAILY) {
            $targets = $divisions->filter(fn ($d) => $this->can($user, Permission::ATTENDANCE_RECORD, $level->id, 'division', $d->id))
                ->map(fn ($d) => ['type' => 'division', 'id' => $d->id, 'label' => $label($d)])->values();
        } else {
            $byId = $divisions->keyBy('id');
            $targets = CourseSection::with('subject:id,name')->where('status', 'active')->whereIn('division_id', $byId->keys())
                ->orderBy('division_id')->get(['id', 'division_id', 'subject_id'])
                ->filter(fn ($s) => $this->can($user, Permission::ATTENDANCE_RECORD, $level->id, 'course_section', $s->id))
                ->map(fn ($s) => ['type' => 'course_section', 'id' => $s->id,
                    'label' => optional($s->subject)->name.' · '.$label($byId[$s->division_id])])->values();
        }

        return ['mode' => $rules['mode'], 'rules' => $rules, 'targets' => $targets];
    }

    /** Planilla de un dia: alumnos con su estado cargado (o sin cargar). */
    public function sheet(User $user, string $type, int $id, string $date): array
    {
        [$level, $target] = $this->authorize($user, $type, $id, $date, false);
        $enrollments = $this->enrollmentsFor($type, $target, $date);
        $records = $this->recordsQuery($type, $target->id, $date)->get()->keyBy('enrollment_id');

        return [
            'type' => $type, 'id' => $target->id, 'date' => $date, 'taken' => $records->isNotEmpty(),
            'can_edit' => $this->editable($user, $level->id, $date),
            'can_justify' => $this->access->allows($user, Permission::ATTENDANCE_JUSTIFY, $level->id, $this->scope($type, $target)),
            'students' => $enrollments->map(function ($e) use ($records) {
                $record = $records[$e->id] ?? null;

                return ['enrollment_id' => $e->id, 'student_id' => $e->student_id,
                    'name' => trim(optional($e->student)->last_name.', '.optional($e->student)->first_name, ', '),
                    'status' => optional($record)->status, 'is_justified' => (bool) optional($record)->is_justified,
                    'justification' => optional($record)->justification, 'record_id' => optional($record)->id];
            })->values(),
        ];
    }

    /** Guarda la planilla del dia completa (alta o correccion). */
    public function save(User $user, string $type, int $id, string $date, array $rows): array
    {
        [$level, $target] = $this->authorize($user, $type, $id, $date, true);
        $enrollments = $this->enrollmentsFor($type, $target, $date)->keyBy('id');
        foreach ($rows as $row) {
            if (! isset($enrollments[(int) $row['enrollment_id']])) {
                throw ValidationException::withMessages(['records' => 'Hay alumnos que no están en esta lista.']);
            }
        }

        $past = $date < now()->toDateString();
        DB::transaction(function () use ($user, $type, $target, $date, $rows, $level, $past) {
            // Serializa la carga de una misma planilla.
            ($type === 'division' ? Division::acrossLevels() : CourseSection::acrossLevels())->whereKey($target->id)->lockForUpdate()->first();
            $existing = $this->recordsQuery($type, $target->id, $date)->lockForUpdate()->get()->keyBy('enrollment_id');
            $changes = 0;
            foreach ($rows as $row) {
                $status = $row['status'];
                $record = $existing[(int) $row['enrollment_id']] ?? null;
                $values = ['status' => $status, 'absence_weight' => AttendanceRecord::WEIGHTS[$status],
                    'recorded_by' => $user->id];
                if ($status === AttendanceRecord::PRESENT) {
                    $values += ['is_justified' => false, 'justification' => null];
                }
                if ($record) {
                    if ($record->status !== $status) {
                        $record->update($values);
                        $changes++;
                    }
                } else {
                    AttendanceRecord::create($values + ['company_id' => $target->company_id, 'school_level_id' => $level->id,
                        'academic_year_id' => $target->academic_year_id, 'enrollment_id' => (int) $row['enrollment_id'],
                        'division_id' => $type === 'division' ? $target->id : $target->division_id,
                        'course_section_id' => $type === 'course_section' ? $target->id : null,
                        'date' => $date, 'source' => 'manual']);
                    $changes++;
                }
            }
            app(Auditor::class)->record('attendance.recorded', $user, ['type' => $type === 'division' ? Division::class : CourseSection::class,
                'id' => $target->id, 'company_id' => $target->company_id, 'school_level_id' => $level->id],
                [], ['date' => $date, 'rows' => count($rows), 'changes' => $changes, 'past_day' => $past],
                $past ? AuditLog::SEVERITY_HIGH : AuditLog::SEVERITY_LOW);
        });

        return $this->sheet($user, $type, $target->id, $date);
    }

    public function justify(User $user, AttendanceRecord $record, ?string $justification): AttendanceRecord
    {
        $level = $this->level();
        abort_unless((int) $record->school_level_id === (int) $level->id, 404);
        $scope = $record->course_section_id ? ['type' => 'course_section', 'id' => (int) $record->course_section_id]
            : ['type' => 'division', 'id' => (int) $record->division_id];
        abort_unless($this->access->allows($user, Permission::ATTENDANCE_JUSTIFY, $level->id, $scope), 403);
        if ($record->status === AttendanceRecord::PRESENT) {
            throw ValidationException::withMessages(['justification' => 'Un presente no se justifica.']);
        }
        $record->update(['is_justified' => $justification !== null && trim($justification) !== '',
            'justification' => $justification !== null && trim($justification) !== '' ? trim($justification) : null]);
        app(Auditor::class)->record('attendance.justified', $user, ['type' => AttendanceRecord::class, 'id' => $record->id,
            'company_id' => $record->company_id, 'school_level_id' => $record->school_level_id], [], ['justified' => $record->is_justified]);

        return $record->fresh();
    }

    /** Ciclos abiertos del nivel actual. */
    public function openYears()
    {
        return DB::table('academic_years')->where('company_id', TenantContext::companyId())
            ->where('school_level_id', TenantContext::schoolLevelId())->whereIn('status', ['active', 'closing'])->pluck('id');
    }

    public function enrollmentsFor(string $type, $target, string $date)
    {
        $divisionId = $type === 'division' ? $target->id : $target->division_id;
        $query = Enrollment::with(['student' => fn ($q) => $q->withoutGlobalScope('school_level')->select('id', 'first_name', 'last_name')])
            ->where('division_id', $divisionId)
            ->where(fn ($q) => $q->where('status', Enrollment::STATUS_ACTIVE)
                ->orWhere(fn ($left) => $left->whereNotNull('left_on')->whereDate('left_on', '>', $date)))
            ->where(fn ($q) => $q->whereNull('enrolled_on')->orWhereDate('enrolled_on', '<=', $date));
        if ($type === 'course_section' && DB::table('section_enrollments')->where('course_section_id', $target->id)->exists()) {
            $query->whereIn('id', DB::table('section_enrollments')->where('course_section_id', $target->id)
                ->where('status', 'active')->select('enrollment_id'));
        }

        return $query->get()->sortBy(fn ($e) => mb_strtolower(optional($e->student)->last_name.' '.optional($e->student)->first_name))->values();
    }

    protected function recordsQuery(string $type, int $id, string $date)
    {
        $query = AttendanceRecord::query()->whereDate('date', $date);

        return $type === 'division' ? $query->where('division_id', $id)->whereNull('course_section_id')
            : $query->where('course_section_id', $id);
    }

    protected function authorize(User $user, string $type, int $id, string $date, bool $writing): array
    {
        $level = $this->level();
        $mode = $this->rules->forLevel($level)['mode'];
        if (($mode === AttendanceRules::DAILY) !== ($type === 'division')) {
            throw ValidationException::withMessages(['type' => $mode === AttendanceRules::DAILY
                ? 'En este nivel la asistencia se toma por día y división.' : 'En este nivel la asistencia se toma por materia.']);
        }
        $target = $type === 'division'
            ? Division::where('enabled', true)->whereIn('academic_year_id', $this->openYears())->find($id)
            : CourseSection::where('status', 'active')->whereIn('academic_year_id', $this->openYears())->find($id);
        abort_unless($target, 404);
        if ($date > now()->toDateString()) {
            throw ValidationException::withMessages(['date' => 'No se puede tomar asistencia de un día futuro.']);
        }
        $scope = $this->scope($type, $target);
        $allowed = $this->access->allows($user, Permission::ATTENDANCE_RECORD, $level->id, $scope)
            || (! $writing && $this->access->allows($user, Permission::ATTENDANCE_VIEW_ALL, $level->id, $scope));
        abort_unless($allowed, 403);
        if ($writing && ! $this->editable($user, $level->id, $date)) {
            abort(403, 'Corregir asistencia de días anteriores requiere autorización de la dirección.');
        }

        return [$level, $target];
    }

    protected function editable(User $user, int $level, string $date): bool
    {
        $limit = now()->subDays($this->rules->forLevel(SchoolLevel::find($level))['edit_window_days'])->toDateString();

        return $date >= $limit || $this->access->allows($user, Permission::ATTENDANCE_AMEND_PAST, $level);
    }

    protected function scope(string $type, $target): array
    {
        return ['type' => $type, 'id' => (int) $target->id];
    }

    protected function can(User $user, string $permission, int $level, string $type, int $id): bool
    {
        return $this->access->allows($user, $permission, $level, ['type' => $type, 'id' => $id]);
    }
}
