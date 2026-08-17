<?php

$root = dirname(__DIR__, 2);

it('reserves institutional level configuration to total administration', function () use ($root) {
    $controller = file_get_contents($root.'/app/Http/Controllers/V1/Settings/SchoolLevelsController.php');
    $settings = file_get_contents($root.'/resources/assets/js/views/settings/SettingsIndex.vue');

    expect($controller)->toContain('Permission::SCHOOL_LEVEL_MANAGE');
    expect($controller)->toContain('$this->access->allows');
    expect($settings)->toContain('totalAdminOnly: true');
    expect($settings)->toContain('visibleMenuItems');
});

it('keeps staff identity separate from optional login accounts', function () use ($root) {
    $migration = file_get_contents($root.'/database/migrations/2026_09_01_001200_create_staff_foundation.php');
    $staff = file_get_contents($root.'/app/Models/StaffMember.php');

    expect($migration)->toContain("Schema::create('staff_members'");
    expect($migration)->toContain("\$table->unsignedBigInteger('user_id')->nullable()");
    expect($staff)->toContain("'user_id'");
    expect($staff)->toContain('function assignments()');
});

it('models staff assignments by institutional level without forcing one level on the person', function () use ($root) {
    $migration = file_get_contents($root.'/database/migrations/2026_09_01_001200_create_staff_foundation.php');
    $assignment = file_get_contents($root.'/app/Models/StaffAssignment.php');

    expect($migration)->toContain("Schema::create('staff_assignments'");
    expect($migration)->toContain("\$table->unsignedBigInteger('school_level_id')->nullable()");
    expect($assignment)->toContain('function schoolLevel()');
    expect($assignment)->toContain("'position_title'");
    expect($assignment)->toContain("'weekly_hours'");
});
