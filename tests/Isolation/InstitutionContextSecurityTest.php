<?php

$root = dirname(__DIR__, 2);

it('reserves the whole institution tenant context to total administration', function () use ($root) {
    $middleware = file_get_contents($root.'/app/Http/Middleware/ValidateTenant.php');
    expect($middleware)->toContain('if (! $isTotalAdmin)');
    expect($middleware)->toContain("'error' => 'school_level_required'");
});

it('hides whole institution from non total admins and forces a concrete allowed level', function () use ($root) {
    $header = file_get_contents($root.'/resources/assets/js/views/layouts/partials/TheSiteHeader.vue');
    expect($header)->toContain('<option v-if="isTotalAdmin" value="">Toda la institución</option>');
    expect($header)->toContain("window.Ls.set('selectedSchoolLevel', this.selectedLevelId)");
});

it('shows institutional levels settings only to total admin while viewing the whole institution', function () use ($root) {
    $settings = file_get_contents($root.'/resources/assets/js/views/settings/SettingsIndex.vue');
    $levels = file_get_contents($root.'/resources/assets/js/views/settings/SchoolLevelsSetting.vue');
    expect($settings)->toContain('this.isTotalAdmin && this.isWholeInstitutionContext');
    expect($levels)->toContain('!this.isTotalAdmin || !this.isWholeInstitutionContext');
});

it('keeps school level editing total-admin-only while allowing scoped level discovery for the header', function () use ($root) {
    $controller = file_get_contents($root.'/app/Http/Controllers/V1/Settings/SchoolLevelsController.php');
    expect($controller)->toContain('can_view_whole_institution');
    expect($controller)->toContain('isTotalAdmin($user)');
    expect($controller)->toContain('$this->authorizeTotalAdmin($request);');
    expect($controller)->toContain("DB::table('school_level_user')");
});
