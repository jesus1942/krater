<?php

$root = dirname(__DIR__, 2);

it('makes Personal operational without destructive delete', function () use ($root) {
    $controller = file_get_contents($root.'/app/Http/Controllers/V1/Staff/StaffMembersController.php');
    $routes = file_get_contents($root.'/routes/api.php');
    $vue = file_get_contents($root.'/resources/assets/js/views/staff/Index.vue');
    expect($routes)->toContain("Route::get('/staff'");
    expect($routes)->toContain("Route::post('/staff'");
    expect($controller)->toContain('Permission::USER_MANAGE');
    expect($controller)->toContain('TenantContext::schoolLevelId()');
    expect($controller)->not->toContain('function destroy');
    expect($vue)->toContain('Alta de personal');
    expect($vue)->toContain('Agregar cargo o función');
});

it('keeps staff identity separate from access account and assignments level scoped', function () use ($root) {
    $controller = file_get_contents($root.'/app/Http/Controllers/V1/Staff/StaffMembersController.php');
    expect($controller)->toContain('assertUserCompany');
    expect($controller)->toContain('assertLevelAllowed');
    expect($controller)->toContain("StaffMember::create");
    expect($controller)->toContain("assignments()->create");
});
