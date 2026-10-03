<?php

$root = dirname(__DIR__, 2);

it('families screen uses family_members as its canonical source', function () use ($root) {
    $controller = file_get_contents($root.'/app/Http/Controllers/V1/Family/FamilyMembersController.php');
    $routes = file_get_contents($root.'/routes/api.php');
    $vue = file_get_contents($root.'/resources/assets/js/views/customers/Index.vue');

    expect($controller)->toContain('FamilyMember::query()');
    expect($controller)->toContain("'students' => function");
    expect($routes)->toContain("Route::get('/family-members'");
    expect($vue)->toContain("/api/v1/family-members");
    expect($vue)->toContain('Los responsables cargados desde el legajo de un alumno aparecerán automáticamente acá.');
});

it('families screen no longer offers destructive customer deletion', function () use ($root) {
    $vue = file_get_contents($root.'/resources/assets/js/views/customers/Index.vue');
    expect($vue)->not->toContain('deleteCustomer');
    expect($vue)->not->toContain('removeCustomer');
    expect($vue)->not->toContain('customers/delete');
});
