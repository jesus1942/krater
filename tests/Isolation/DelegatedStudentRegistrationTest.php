<?php

namespace Tests\Isolation;

use Crater\Models\AuditLog;
use Crater\Services\Access\AccessManager;
use Illuminate\Support\Facades\DB;

/** Regresiones HTTP de alta delegada: permiso, alcance y transaccion reales. */
class DelegatedStudentRegistrationTest extends LegacyRoutesSecurityTest
{
    private function payload(int $division = 1): array
    {
        return ['first_name' => 'Alta', 'last_name' => 'Ficticia', 'dni' => 'R2B-NUEVO',
            'birth_date' => '2016-01-01', 'academic_year_id' => 1, 'grade_level_id' => 1,
            'division_id' => $division, 'status' => 'active'];
    }

    private function delegate(): int
    {
        $this->loginAs($this->director);
        return $this->postJson('/api/v1/users/'.$this->preceptor->id.'/role-assignments', [
            'role_id' => DB::table('roles')->where('company_id', 1)->where('name', 'preceptor_registrar')->value('id'),
            'school_level_id' => 1, 'division_id' => 1,
        ])->assertStatus(201)->json('id');
    }

    public function test_plain_preceptor_has_no_high_actions_but_reads_academic_catalogs(): void
    {
        $this->loginAs($this->preceptor);
        $this->postJson('/api/v1/students', $this->payload())->assertStatus(403);
        $this->getJson('/api/v1/students/placement-options')->assertStatus(403);
        foreach (['academic-years', 'grade-levels', 'subjects', 'study-plans', 'divisions', 'enrollments?division_id=1'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertStatus(200);
        }
        $this->getJson('/api/v1/students')->assertStatus(200)->assertJsonPath('summary.total', 1)
            ->assertJsonPath('students.data.0.can_edit', false);
    }

    public function test_delegation_creates_student_and_initial_enrollment_only_in_its_division(): void
    {
        $this->delegate();
        $this->loginAs($this->preceptor);
        $this->getJson('/api/v1/students/placement-options')->assertStatus(200)->assertJsonCount(1, 'divisions')
            ->assertJsonPath('divisions.0.id', 1);
        $id = $this->postJson('/api/v1/students', $this->payload())->assertStatus(201)->json('student.id');
        $this->assertSame(1, DB::table('enrollments')->where('student_id', $id)->where('division_id', 1)->count());
        $this->getJson('/api/v1/students/'.$id)->assertStatus(200)->assertJsonPath('student.can_edit', true);
        $out = $this->payload(3); $out['dni'] = 'R2B-OTRA';
        $this->postJson('/api/v1/students', $out)->assertStatus(403);
        $other = $this->payload(2); $other['academic_year_id'] = 2; $other['grade_level_id'] = 2; $other['dni'] = 'R2B-SEC';
        $this->postJson('/api/v1/students', $other)->assertStatus(403);
        $this->postJson('/api/v1/students', $this->payload(), ['school-level' => '2'])->assertStatus(403);
        $this->assertSame(4, DB::table('students')->count());
    }

    public function test_basic_edit_cannot_change_status_family_notes_or_placement(): void
    {
        $this->delegate();
        $this->loginAs($this->preceptor);
        $basic = ['first_name' => 'Editado', 'last_name' => 'Ficticio'];
        $this->putJson('/api/v1/students/1', $basic)->assertStatus(200);
        foreach (['status' => 'withdrawn', 'division_id' => 3, 'school_level_id' => 2,
            'guardian_id' => $this->secondaryCustomer->id, 'notes' => 'dato extra', 'family_members' => []] as $key => $value) {
            $this->putJson('/api/v1/students/1', $basic + [$key => $value])->assertStatus(403);
        }
        $this->putJson('/api/v1/students/3', $basic)->assertStatus(403);
        $this->deleteJson('/api/v1/students/1')->assertStatus(403);
        $this->putJson('/api/v1/students/1/relocate', $this->payload())->assertStatus(403);
        $this->postJson('/api/v1/enrollments', ['student_id' => 1, 'division_id' => 3])->assertStatus(403);
        $this->getJson('/api/v1/students')->assertStatus(200);
        $this->assertSame(1, (int) DB::table('enrollments')->where('student_id', 1)->value('division_id'));
    }

    public function test_vicedirection_and_preceptor_cannot_grant_or_revoke_delegation_even_with_extra_permission(): void
    {
        $id = $this->delegate();
        $vice = $this->account('vice-r2b'); $this->assign($vice, 'vice_director', 1);
        foreach (['vice_director', 'preceptor'] as $role) {
            DB::table('permission_role')->insert(['role_id' => DB::table('roles')->where('company_id', 1)->where('name', $role)->value('id'),
                'permission_id' => DB::table('permissions')->where('name', 'system.role.assign')->value('id')]);
        }
        foreach ([$vice, $this->preceptor] as $actor) {
            $this->loginAs($actor);
            $this->postJson('/api/v1/users/'.$this->secondary->id.'/role-assignments', [
                'role_id' => DB::table('roles')->where('company_id', 1)->where('name', 'preceptor_registrar')->value('id'),
                'school_level_id' => 1, 'division_id' => 1])->assertStatus(403);
            $this->postJson('/api/v1/role-assignments/'.$id.'/revoke')->assertStatus(403);
        }
    }

    public function test_revoke_is_immediate_and_expiry_never_confers_registration(): void
    {
        $id = $this->delegate();
        $this->loginAs($this->preceptor);
        $this->getJson('/api/v1/students/placement-options')->assertStatus(200);
        $this->loginAs($this->director);
        $this->postJson('/api/v1/role-assignments/'.$id.'/revoke')->assertStatus(200);
        $this->loginAs($this->preceptor);
        $this->postJson('/api/v1/students', $this->payload())->assertStatus(403);
        $this->putJson('/api/v1/students/1', ['first_name' => 'Intento', 'last_name' => 'Revocado'])->assertStatus(403);
        DB::table('role_user')->where('id', $id)->update(['revoked_at' => null, 'ends_on' => now()->subDay()->toDateString()]);
        $this->assertFalse(app(AccessManager::class)->allows($this->preceptor, 'students.register', 1));
    }

    public function test_capacity_and_audit_failure_do_not_leave_orphan_students(): void
    {
        $this->delegate();
        $this->loginAs($this->preceptor);
        DB::table('divisions')->where('id', 1)->update(['capacity' => 1]);
        $this->postJson('/api/v1/students', $this->payload())->assertStatus(422);
        $this->assertSame(3, DB::table('students')->count());
        DB::table('divisions')->where('id', 1)->update(['capacity' => 30]);
        AuditLog::creating(function ($log) {
            if ($log->action === 'student_registered') throw new \RuntimeException('auditoria simulada');
        });
        try {
            $this->postJson('/api/v1/students', $this->payload())->assertStatus(500);
            $this->assertSame(3, DB::table('students')->count());
            $this->assertSame(3, DB::table('enrollments')->count());
        } finally { AuditLog::flushEventListeners(); }
    }
    public function test_extra_role_loses_authority_when_base_preceptor_is_revoked(): void
    {
        $this->delegate();
        DB::table('role_user')->where('user_id', $this->preceptor->id)
            ->where('role_id', DB::table('roles')->where('company_id', 1)->where('name', 'preceptor')->value('id'))
            ->update(['revoked_at' => now()]);
        $this->loginAs($this->preceptor);
        $this->getJson('/api/v1/students/placement-options')->assertStatus(403);
        $this->postJson('/api/v1/students', $this->payload())->assertStatus(403);
        $this->assertNotContains('students.register', app(AccessManager::class)->effectivePermissions($this->preceptor, 1));
    }

    public function test_role_experience_fixtures_are_repeatable_and_preserve_revocations(): void
    {
        $this->app['env'] = 'staging';
        DB::connection()->setDatabaseName('krater_staging');
        config(['staging.admin_password' => 'clave-ficticia-exclusiva-del-test-0123456789']);
        DB::table('companies')->where('id', 1)->update(['unique_hash' => 'suiteena-staging-fixture']);
        $service = app(\Crater\Services\Access\StagingAccessFixtures::class);
        $fixture = $service->prepareRoleExperience(1);
        $role = DB::table('role_user')->where('user_id', $fixture['teacher'])->value('id');
        DB::table('role_user')->where('id', $role)->update(['revoked_at' => now()]);
        $count = DB::table('users')->count();
        $this->assertSame($fixture, $service->prepareRoleExperience(1));
        $this->assertSame($count, DB::table('users')->count());
        $this->assertNotNull(DB::table('role_user')->where('id', $role)->value('revoked_at'));
    }

}
