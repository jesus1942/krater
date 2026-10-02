<?php

namespace Crater\Services\Access;

use Crater\Enums\Permission;
use Crater\Models\AcademicYear;
use Crater\Models\AuditLog;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\Student;
use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Alta delegada: identidad y matricula inicial atomicas, sin modificar familiares. */
class StudentRegistration
{
    /** Revalida autoridad y cupo bajo los mismos bloqueos que roles y matriculas. */
    public function create(User $actor, array $data): Student
    {
        return DB::transaction(function () use ($actor, $data) {
            app(RoleAssignments::class)->lockInstitution(TenantContext::companyId());
            $actor = User::findOrFail($actor->id);
            $division = Division::where('company_id', TenantContext::companyId())
                ->where('school_level_id', TenantContext::schoolLevelId())->lockForUpdate()->find($data['division_id']);
            abort_unless($division && app(AccessManager::class)->allows($actor, Permission::STUDENT_REGISTER,
                TenantContext::schoolLevelId(), ['type' => 'division', 'id' => $division->id]), 403);
            $year = AcademicYear::where('company_id', TenantContext::companyId())->find($data['academic_year_id']);
            abort_unless($year && ! $year->isClosed() && $division->enabled
                && (int) $division->academic_year_id === (int) $year->id
                && (int) $division->grade_level_id === (int) $data['grade_level_id'], 422, 'La ubicacion academica no es valida.');
            if (! $division->hasCapacity()) {
                throw ValidationException::withMessages(['division_id' => 'No quedan lugares en esta division.']);
            }
            $student = Student::create(array_merge($this->basicData($data), [
                'company_id' => TenantContext::companyId(), 'school_level_id' => TenantContext::schoolLevelId(),
                'status' => $data['status'] ?? 'active', 'school_year' => $year->year,
                'level' => $division->schoolLevel->name, 'grade' => $division->gradeLevel->name, 'division' => $division->name,
            ]));
            Enrollment::create(['company_id' => TenantContext::companyId(), 'school_level_id' => TenantContext::schoolLevelId(),
                'academic_year_id' => $year->id, 'division_id' => $division->id, 'student_id' => $student->id,
                'status' => Enrollment::STATUS_ACTIVE, 'enrolled_on' => now()->toDateString(), 'attendance_condition' => 'regular']);
            $this->audit($actor, $student, 'student_registered', ['division_id' => $division->id, 'academic_year_id' => $year->id]);
            return $student;
        });
    }

    /** Edita solo identidad basica; la ubicacion, estado y legajo extra se conservan. */
    public function update(User $actor, Student $student, array $data): Student
    {
        return DB::transaction(function () use ($actor, $student, $data) {
            app(RoleAssignments::class)->lockInstitution(TenantContext::companyId());
            $actor = User::findOrFail($actor->id);
            $student = Student::where('company_id', TenantContext::companyId())->lockForUpdate()->findOrFail($student->id);
            $access = app(AccessManager::class);
            abort_unless($student->enrollments()->active()->whereIn('division_id',
                $access->scopedDivisionIds($actor, Permission::STUDENT_REGISTER, TenantContext::schoolLevelId()))->exists(), 403);
            $student->update($this->basicData($data));
            $this->audit($actor, $student, 'student_basic_updated', ['fields' => array_keys($this->basicData($data))]);
            return $student;
        });
    }

    /** Lista cerrada: ningun campo financiero, familiar o sensible entra por el alta. */
    private function basicData(array $data): array
    {
        return array_intersect_key($data, array_flip(['first_name', 'last_name', 'dni', 'birth_date']));
    }

    /** Auditoria obligatoria sin PII; una falla revierte el alta o la edicion. */
    private function audit(User $actor, Student $student, string $action, array $values): void
    {
        AuditLog::create(['company_id' => TenantContext::companyId(), 'school_level_id' => TenantContext::schoolLevelId(),
            'user_id' => $actor->id, 'action' => $action, 'auditable_type' => Student::class, 'auditable_id' => $student->id,
            'new_values' => $values, 'severity' => AuditLog::SEVERITY_HIGH, 'created_at' => now()]);
    }
}
