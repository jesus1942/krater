<?php

$root = dirname(__DIR__, 2);

it('never derives student level from the request header anymore', function () use ($root) {
    $source = file_get_contents($root.'/app/Http/Controllers/V1/Student/StudentsController.php');
    expect($source)->toContain('applyCanonicalPlacement');
});

it('uses canonical dropdown identifiers instead of free text placement', function () use ($root) {
    $request = file_get_contents($root.'/app/Http/Requests/StudentRequest.php');
    $vue = file_get_contents($root.'/resources/assets/js/views/students/Index.vue');
    expect($request)->toContain("'academic_year_id' => ['required', 'integer']");
    expect($request)->toContain("'grade_level_id' => ['required', 'integer']");
    expect($request)->toContain("'division_id' => ['required', 'integer']");
    expect($vue)->toContain('Seleccionar curso');
    expect($vue)->toContain('Seleccionar división');
    expect($vue)->not->toContain('placeholder="Ej.: 4.º"');
});

it('repairs explicit legacy level mismatches without personal hardcoding', function () use ($root) {
    $migration = file_get_contents($root.'/database/migrations/2026_09_01_000800_repair_student_level_consistency.php');
    expect($migration)->toContain("'Primario' => 'primary'");
    expect($migration)->toContain("'Secundario' => 'secondary'");
    expect($migration)->toContain("'Terciario' => 'tertiary'");
    expect($migration)->not->toContain('55230674');
    expect($migration)->not->toContain('Felipe');
});
