<?php

namespace Crater\Policies;

use Crater\Enums\Permission;
use Crater\Models\AcademicYear;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Autorizacion de la estructura academica.
 *
 * Las policies NO reimplementan logica de permisos: traducen "puede este
 * usuario tocar este objeto" a una pregunta de permiso mas alcance, y delegan
 * en AccessManager. Si hay un solo lugar donde se decide, hay un solo lugar
 * donde puede haber un agujero.
 *
 * Se usa una policy por dominio en vez de una por modelo porque las reglas son
 * las mismas para todos los objetos de estructura y repetirlas seis veces
 * invita a que se desincronicen.
 */
class AcademicPolicy
{
    use HandlesAuthorization;

    protected $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    // --- ciclo lectivo -------------------------------------------------------

    public function viewAnyAcademicYear(User $user): bool
    {
        return $this->access->allows($user, Permission::ACADEMIC_YEAR_VIEW, $this->level());
    }

    public function viewAcademicYear(User $user, AcademicYear $year): bool
    {
        return $this->access->allows($user, Permission::ACADEMIC_YEAR_VIEW, $year->school_level_id);
    }

    public function manageAcademicYear(User $user, ?AcademicYear $year = null): bool
    {
        // Un ciclo cerrado no se edita ni con el permiso: es historia. Para
        // tocarlo hay que reabrirlo, que es una accion con doble control.
        if ($year && $year->isClosed()) {
            return false;
        }

        return $this->access->allows(
            $user,
            Permission::ACADEMIC_YEAR_MANAGE,
            $year ? $year->school_level_id : $this->level()
        );
    }

    public function closeAcademicYear(User $user, AcademicYear $year): bool
    {
        if ($year->isClosed()) {
            return false;
        }

        return $this->access->allows($user, Permission::ACADEMIC_YEAR_CLOSE, $year->school_level_id);
    }

    // --- divisiones ----------------------------------------------------------

    public function viewAnyDivision(User $user): bool
    {
        return $this->access->allows($user, Permission::DIVISION_VIEW, $this->level());
    }

    /**
     * Ver una division concreta. Aca entra el alcance fino: un preceptor solo
     * ve las divisiones que tiene asignadas en `user_scopes`, y un docente
     * alcanza por tener alguna seccion de esa division.
     */
    public function viewDivision(User $user, Division $division): bool
    {
        return $this->access->allows(
            $user,
            Permission::DIVISION_VIEW,
            $division->school_level_id,
            ['type' => 'division', 'id' => $division->id]
        );
    }

    public function manageDivision(User $user, ?Division $division = null): bool
    {
        if ($division && optional($division->academicYear)->isClosed()) {
            return false;
        }

        return $this->access->allows(
            $user,
            Permission::DIVISION_MANAGE,
            $division ? $division->school_level_id : $this->level()
        );
    }

    // --- secciones de materia -------------------------------------------------

    public function viewSection(User $user, CourseSection $section): bool
    {
        return $this->access->allows(
            $user,
            Permission::SECTION_VIEW,
            $section->school_level_id,
            ['type' => 'course_section', 'id' => $section->id]
        );
    }

    public function manageSection(User $user, ?CourseSection $section = null): bool
    {
        return $this->access->allows(
            $user,
            Permission::SECTION_MANAGE,
            $section ? $section->school_level_id : $this->level()
        );
    }

    public function assignTeacher(User $user, CourseSection $section): bool
    {
        return $this->access->allows(
            $user,
            Permission::SECTION_ASSIGN_TEACHER,
            $section->school_level_id
        );
    }

    // --- planes y materias -----------------------------------------------------

    public function viewStudyPlan(User $user): bool
    {
        return $this->access->allows($user, Permission::STUDY_PLAN_VIEW, $this->level());
    }

    public function manageStudyPlan(User $user): bool
    {
        return $this->access->allows($user, Permission::STUDY_PLAN_MANAGE, $this->level());
    }

    /**
     * Nivel del contexto actual. Se lee del header ya validado por
     * ValidateTenant; para objetos concretos se usa el nivel del objeto, que es
     * mas confiable.
     */
    protected function level(): ?int
    {
        $level = \Crater\Support\TenantContext::schoolLevelId();

        return $level ? (int) $level : null;
    }
}
