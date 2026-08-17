<?php

use Crater\Enums\Permission;
use Crater\Enums\RoleName;

$projectRoot = dirname(__DIR__, 2);

it('define permisos propios de recursos humanos', function () {
    expect(Permission::HR_STAFF_VIEW)->toBe('hr.staff.view')
        ->and(Permission::HR_STAFF_MANAGE)->toBe('hr.staff.manage')
        ->and(Permission::HR_PAYROLL_VIEW)->toBe('hr.payroll.view')
        ->and(Permission::HR_PAYROLL_MANAGE)->toBe('hr.payroll.manage')
        ->and(Permission::HR_PAYROLL_APPROVE)->toBe('hr.payroll.approve')
        ->and(Permission::HR_PAYROLL_PAY)->toBe('hr.payroll.pay');
});

it('mantiene recursos humanos separado de administracion economica', function () {
    $roles = RoleName::definitions();
    expect($roles)->toHaveKey(RoleName::HR_ADMIN);
    expect($roles[RoleName::HR_ADMIN]['permissions'])->toContain(Permission::HR_PAYROLL_MANAGE);
    expect($roles[RoleName::FINANCE_ADMIN]['permissions'])->not->toContain(Permission::HR_PAYROLL_MANAGE);
});

it('expone rutas de liquidacion sin deletes destructivos', function () use ($projectRoot) {
    $routes = file_get_contents($projectRoot.'/routes/api.php');
    expect($routes)->toContain("/payroll/periods")
        ->and($routes)->toContain("/payroll/slips/{payrollSlip}/payments")
        ->and($routes)->toContain("/payroll/payments/{payrollPayment}/reverse")
        ->and($routes)->not->toContain("Route::delete('/payroll");
});

it('muestra liquidaciones como modulo propio de RRHH', function () use ($projectRoot) {
    $sidebar = file_get_contents($projectRoot.'/resources/assets/js/views/layouts/partials/TheSiteSidebar.vue');
    $router = file_get_contents($projectRoot.'/resources/assets/js/router.js');
    expect($sidebar)->toContain('Recursos Humanos')
        ->and($sidebar)->toContain('/admin/payroll')
        ->and($router)->toContain("path: 'payroll'");
});
