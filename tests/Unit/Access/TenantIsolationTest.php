<?php

use Crater\Http\Middleware\Authenticate;
use Crater\Http\Middleware\ValidateTenant;
use Crater\Models\AcademicYear;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\User;
use Crater\Policies\AcademicPolicy;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Routing\Middleware\SubstituteBindings;

/**
 * Regresiones del aislamiento multiinstitucion.
 *
 * Estas pruebas cubren las dos barreras que tienen que mantenerse juntas:
 *
 * 1. TenantContext se fija antes de que Laravel resuelva un ID de ruta.
 * 2. La policy vuelve a comparar la empresa del recurso con la del usuario.
 *
 * Si una falla, un ID valido de otra institucion podria llegar al controlador.
 */

it('valida el tenant antes de resolver los modelos de la ruta', function () {
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $reflection = new ReflectionClass($kernel);
    $property = $reflection->getProperty('middlewarePriority');
    $property->setAccessible(true);

    $priority = $property->getValue($kernel);

    expect($priority)->toContain(Authenticate::class);
    expect($priority)->toContain(ValidateTenant::class);
    expect($priority)->toContain(SubstituteBindings::class);

    $authentication = array_search(Authenticate::class, $priority, true);
    $tenant = array_search(ValidateTenant::class, $priority, true);
    $bindings = array_search(SubstituteBindings::class, $priority, true);

    expect($authentication < $tenant)->toBeTrue();
    expect($tenant < $bindings)->toBeTrue();
});

it('agrega el filtro de empresa a las consultas academicas', function () {
    TenantContext::set(41, null);

    try {
        // applyScopes devuelve el builder con los scopes globales materializados.
        // Consultar los bindings del builder original no los incluiria.
        $query = AcademicYear::query()->applyScopes();

        expect($query->toSql())->toContain('company_id');
        expect($query->getBindings())->toContain(41);
    } finally {
        TenantContext::clear();
    }
});

it('niega objetos de otra institucion antes de consultar permisos', function () {
    $access = new class extends AccessManager {
        public $called = false;

        public function allows(
            User $user,
            string $permission,
            ?int $schoolLevelId = null,
            ?array $scope = null
        ): bool {
            $this->called = true;

            return true;
        }
    };

    $policy = new AcademicPolicy($access);

    $user = new User();
    $user->forceFill(['company_id' => 1]);

    $year = new AcademicYear();
    $year->forceFill([
        'company_id' => 2,
        'school_level_id' => 20,
        'status' => AcademicYear::STATUS_DRAFT,
    ]);

    $division = new Division();
    $division->forceFill([
        'company_id' => 2,
        'school_level_id' => 20,
    ]);

    $section = new CourseSection();
    $section->forceFill([
        'company_id' => 2,
        'school_level_id' => 20,
    ]);

    expect($policy->viewAcademicYear($user, $year))->toBeFalse();
    expect($policy->manageAcademicYear($user, $year))->toBeFalse();
    expect($policy->closeAcademicYear($user, $year))->toBeFalse();

    expect($policy->viewDivision($user, $division))->toBeFalse();
    expect($policy->manageDivision($user, $division))->toBeFalse();

    expect($policy->viewSection($user, $section))->toBeFalse();
    expect($policy->manageSection($user, $section))->toBeFalse();
    expect($policy->assignTeacher($user, $section))->toBeFalse();

    expect($access->called)->toBeFalse();
});
