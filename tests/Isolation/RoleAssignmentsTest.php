<?php

namespace Tests\Isolation;

use Crater\Models\AuditLog;
use Crater\Services\Access\AccessManager;
use Illuminate\Support\Facades\DB;

/** Usa las mismas cuentas y esquema de R1 para cubrir el flujo R2 completo. */
class RoleAssignmentsTest extends LegacyRoutesSecurityTest
{
    public function test_accounts_without_language_use_spanish_in_bootstrap_and_profile(): void
    {
        require_once database_path('migrations/2017_05_06_173745_create_countries_table.php');
        (new \CreateCountriesTable())->up();
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('es', config('app.fallback_locale'));
        foreach (['currency' => '1', 'moment_date_format' => 'DD/MM/YYYY', 'fiscal_year' => '1-12', 'time_zone' => 'America/Argentina/Buenos_Aires'] as $option => $value) {
            DB::table('company_settings')->insert(['company_id' => 1, 'option' => $option, 'value' => $value]);
        }
        $this->loginAs($this->preceptor);
        foreach ([null, '', 'unsupported', 'en', 'es'] as $language) {
            DB::table('user_settings')->where('user_id', $this->preceptor->id)->delete();
            if ($language !== null) $this->preceptor->setSettings(['language' => $language]);
            $expected = in_array($language, ['en', 'es'], true) ? $language : 'es';
            $this->getJson('/api/v1/bootstrap')->assertStatus(200)->assertJsonPath('default_language', $expected);
            $this->getJson('/api/v1/me/settings?settings%5B0%5D=language')->assertStatus(200)->assertJsonPath('language', $expected);
        }
    }

    public function test_new_accounts_start_in_spanish_even_if_company_has_legacy_english_setting(): void
    {
        DB::table('company_settings')->insert(['company_id' => 1, 'option' => 'language', 'value' => 'en']);
        $this->loginAs($this->admin);
        $new = $this->postJson('/api/v1/users', ['name' => 'Cuenta ficticia', 'email' => 'idioma@example.invalid',
            'password' => 'clave-ficticia-1234'])->assertStatus(200)->json('user.id');
        $this->assertSame('es', DB::table('user_settings')->where('user_id', $new)->where('key', 'language')->value('value'));
    }

    public function test_validation_messages_use_spanish_without_language_preference(): void
    {
        $this->loginAs($this->admin);
        $this->postJson('/api/v1/users', [])->assertStatus(422)
            ->assertJsonPath('message', 'Los datos ingresados no son válidos.')
            ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
        $this->assertSame('Las credenciales no coinciden con nuestros registros.', __('auth.failed'));
        $this->assertSame('Siguiente &raquo;', __('pagination.next'));
    }

    private function payload(string $role = 'preceptor', ?int $level = 1, ?int $division = 1): array
    {
        return ['role_id' => DB::table('roles')->where('company_id', 1)->where('name', $role)->value('id'),
            'school_level_id' => $level, 'division_id' => $division, 'starts_at' => now()->toDateString()];
    }

    public function test_r2_grant_revoke_regrant_preserves_history_and_retires_scope(): void
    {
        $target = $this->account('nuevo');
        $this->loginAs($this->admin);
        $id = $this->postJson('/api/v1/users/'.$target->id.'/role-assignments', $this->payload())->assertStatus(201)->json('id');
        $access = app(AccessManager::class);
        $this->assertTrue($access->allows($target, 'students.view_basic', 1));
        $this->assertSame([1], $access->scopedDivisionIds($target));
        $this->postJson('/api/v1/users/'.$target->id.'/role-assignments', $this->payload())->assertStatus(409);
        $this->getJson('/api/v1/users/'.$target->id.'/effective-permissions')->assertStatus(200)
            ->assertJsonFragment(['label' => 'Ver datos basicos de estudiantes']);
        $this->postJson('/api/v1/role-assignments/'.$id.'/revoke')->assertStatus(200);
        $this->assertFalse($access->hasActiveRole($target));
        $this->assertSame([], $access->scopedDivisionIds($target));
        $this->postJson('/api/v1/users/'.$target->id.'/role-assignments', $this->payload('preceptor', 1, 3))->assertStatus(201);
        $this->assertSame([3], $access->scopedDivisionIds($target));
        $this->getJson('/api/v1/users/'.$target->id.'/role-assignments')->assertStatus(200)->assertJsonCount(2, 'assignments');
        $this->assertSame(2, DB::table('role_user')->where('user_id', $target->id)->count());
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'role_granted')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'role_revoked')->count());
    }

    public function test_r2_director_cannot_grant_equal_higher_foreign_or_self_roles(): void
    {
        $this->loginAs($this->director);
        $endpoint = '/api/v1/users/'.$this->preceptor->id.'/role-assignments';
        foreach ([$this->payload('total_admin', null, null), $this->payload('level_director', 1, null),
            $this->payload('general_director', null, null), $this->payload('staff', 2, null),
            $this->payload('preceptor', 1, 2)] as $payload) {
            $this->postJson($endpoint, $payload)->assertStatus(403);
        }
        $foreign = $this->payload('staff', 1, null);
        $foreign['role_id'] = DB::table('roles')->where('company_id', 2)->where('name', 'staff')->value('id');
        $this->postJson($endpoint, $foreign)->assertStatus(403);
        $this->postJson('/api/v1/users/'.$this->director->id.'/role-assignments', $this->payload('staff', 1, null))->assertStatus(403);
        $this->postJson('/api/v1/users/'.$this->secondary->id.'/role-assignments', $this->payload('staff', 1, null))->assertStatus(403);
        $this->postJson('/api/v1/users/'.$this->admin->id.'/role-assignments', $this->payload('staff', 1, null))->assertStatus(403);
        $this->postJson($endpoint, $this->payload('staff', 1, null))->assertStatus(201);
        $this->getJson('/api/v1/roles')->assertStatus(200)->assertJsonCount(1, 'levels');
    }

    public function test_r2_expired_scheduled_and_revoked_assignments_never_grant_authority(): void
    {
        $target = $this->account('fechas');
        $this->loginAs($this->admin);
        $payload = $this->payload();
        $payload['starts_at'] = now()->addDay()->toDateString();
        $id = $this->postJson('/api/v1/users/'.$target->id.'/role-assignments', $payload)->assertStatus(201)->json('id');
        $access = app(AccessManager::class);
        $this->assertFalse($access->hasActiveRole($target));
        $this->assertSame([], $access->scopedDivisionIds($target));
        $this->postJson('/api/v1/role-assignments/'.$id.'/revoke')->assertStatus(200);
        $payload['starts_at'] = now()->subDays(4)->toDateString();
        $payload['ends_at'] = now()->subDay()->toDateString();
        $this->postJson('/api/v1/users/'.$target->id.'/role-assignments', $payload)->assertStatus(201);
        $this->assertFalse($access->hasActiveRole($target));
        $this->assertSame([], $access->effectivePermissions($target, 1));
        $payload['ends_at'] = now()->subDays(7)->toDateString();
        $this->postJson('/api/v1/users/'.$target->id.'/role-assignments', $payload)->assertStatus(422);
    }

    public function test_r2_total_admin_cannot_revoke_self_or_leave_only_an_expiring_admin(): void
    {
        $this->loginAs($this->admin);
        $own = DB::table('role_user')->where('user_id', $this->admin->id)->value('id');
        $this->postJson('/api/v1/role-assignments/'.$own.'/revoke')->assertStatus(403);
        $second = $this->account('segundo-admin');
        $id = $this->postJson('/api/v1/users/'.$second->id.'/role-assignments', $this->payload('total_admin', null, null))->assertStatus(201)->json('id');
        $this->loginAs($second);
        $this->postJson('/api/v1/role-assignments/'.$own.'/revoke')->assertStatus(200);
        $this->assertFalse(app(AccessManager::class)->isTotalAdmin($this->admin));
        $this->assertTrue(app(AccessManager::class)->isTotalAdmin($second));
        // Caso legacy: otro total admin vigente hoy, pero con vencimiento.
        DB::table('role_user')->where('id', $own)->update(['revoked_at' => null, 'ends_on' => now()->addDay()->toDateString()]);
        $this->loginAs($this->admin);
        $this->postJson('/api/v1/role-assignments/'.$id.'/revoke')->assertStatus(409);
        $this->postJson('/api/v1/users/delete', ['users' => [$second->id]])->assertStatus(409);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_r2_audit_failure_rolls_back_the_assignment(): void
    {
        $target = $this->account('auditoria');
        $this->loginAs($this->admin);
        AuditLog::creating(function ($log) {
            if ($log->action === 'role_granted') {
                throw new \RuntimeException('falla simulada de auditoria');
            }
        });
        try {
            $this->postJson('/api/v1/users/'.$target->id.'/role-assignments', $this->payload())->assertStatus(500);
            $this->assertSame(0, DB::table('role_user')->where('user_id', $target->id)->count());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_r2_preceptor_cannot_inspect_or_mutate_assignments(): void
    {
        $this->loginAs($this->preceptor);
        foreach (['/roles', '/users/'.$this->admin->id.'/role-assignments', '/users/'.$this->admin->id.'/effective-permissions'] as $path) {
            $this->getJson('/api/v1'.$path)->assertStatus(403);
        }
        $this->postJson('/api/v1/users/'.$this->preceptor->id.'/role-assignments', $this->payload('total_admin', null, null))->assertStatus(403);
        $this->postJson('/api/v1/role-assignments/1/revoke')->assertStatus(403);
    }

    public function test_r2_frontend_access_ignores_legacy_labels(): void
    {
        $access = app(AccessManager::class);
        $target = $this->account('super-falso', 'super admin');
        $this->assertFalse($access->frontendAccess($target)['is_total_admin']);
        $this->assertSame([], $access->frontendAccess($target)['permissions']);
        $this->assertTrue($access->frontendAccess($this->admin)['is_total_admin']);
        $this->assertArrayHasKey(1, $access->frontendAccess($this->preceptor)['permissions_by_level']);
        $this->assertArrayNotHasKey(2, $access->frontendAccess($this->preceptor)['permissions_by_level']);
    }
    public function test_r2_unrelated_level_role_does_not_expand_preceptor_permissions(): void
    {
        $this->loginAs($this->admin);
        // RR.HH. no concede acceso al legajo: no debe ampliar el preceptor.
        $this->postJson('/api/v1/users/'.$this->preceptor->id.'/role-assignments', $this->payload('hr_admin', 1, null))->assertStatus(201);
        $this->loginAs($this->preceptor);
        $this->getJson('/api/v1/students')->assertStatus(200)->assertJsonCount(1, 'students.data');
    }

    public function test_r2_new_accounts_have_no_authority_and_total_admin_cannot_be_scheduled(): void
    {
        $this->loginAs($this->admin);
        $new = $this->postJson('/api/v1/users', ['name' => 'Cuenta de prueba', 'email' => 'r2@example.invalid',
            'password' => 'clave-ficticia-1234'])->assertStatus(200)->json('user.id');
        $this->getJson('/api/v1/users/'.$new.'/role-assignments')->assertStatus(200)->assertJsonCount(0, 'assignments');
        $payload = $this->payload('total_admin', null, null);
        $payload['ends_at'] = now()->addMonth()->toDateString();
        $this->postJson('/api/v1/users/'.$new.'/role-assignments', $payload)->assertStatus(422);
        $payload['ends_at'] = null;
        $payload['starts_at'] = now()->addDay()->toDateString();
        $this->postJson('/api/v1/users/'.$new.'/role-assignments', $payload)->assertStatus(422);
    }

    public function test_r2_teacher_section_is_bound_to_its_own_assignment(): void
    {
        DB::table('subjects')->insert(['id' => 1, 'company_id' => 1, 'school_level_id' => 1, 'name' => 'Materia ficticia', 'code' => 'R2']);
        DB::table('course_sections')->insert(['id' => 1, 'company_id' => 1, 'school_level_id' => 1,
            'academic_year_id' => 1, 'division_id' => 1, 'subject_id' => 1]);
        $target = $this->account('docente-r2');
        $this->loginAs($this->admin);
        $payload = $this->payload('teacher', 1, null);
        $payload['course_section_id'] = 1;
        $id = $this->postJson('/api/v1/users/'.$target->id.'/role-assignments', $payload)->assertStatus(201)->json('id');
        $this->assertSame([1], app(AccessManager::class)->scopedDivisionIds($target));
        $this->assertTrue(app(AccessManager::class)->allows($target, 'grading.grade.record', 1, ['type' => 'course_section', 'id' => 1]));
        $this->assertFalse(app(AccessManager::class)->allows($target, 'grading.grade.record', 1, ['type' => 'course_section', 'id' => 2]));
        $this->postJson('/api/v1/role-assignments/'.$id.'/revoke')->assertStatus(200);
        $this->assertSame([], app(AccessManager::class)->scopedDivisionIds($target));
    }

}
