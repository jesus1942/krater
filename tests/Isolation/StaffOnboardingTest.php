<?php

namespace Tests\Isolation;

use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\CreatesApplication;

/** Fila E1: alta unificada de personal, cargos, docencia y acceso. */
class StaffOnboardingTest extends TestCase
{
    use CreatesApplication;

    private User $admin;
    private User $director;
    private array $divisions = [];
    private array $sections = [];
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'queue.default' => 'sync', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite'); TenantContext::clear();
        Artisan::call('migrate', ['--force' => true]);
        // Misma correccion de PK de SQLite que WholeInstitutionTest.
        foreach (['items', 'invoice_items', 'estimate_items', 'estimates', 'expenses', 'company_settings'] as $table) {
            $sql = DB::selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$table])->sql;
            if (strpos($sql, 'PRIMARY KEY(') !== false) {
                $sql = preg_replace('/, PRIMARY KEY\([^)]*\)/', '', $sql);
                $sql = preg_replace('/\bid INTEGER NOT NULL\b/', 'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT', $sql, 1);
                DB::statement('DROP TABLE '.$table); DB::statement($sql);
            }
        }
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Bus::fake();
        DB::table('companies')->insert(['id' => 1, 'name' => 'Institucion ficticia', 'unique_hash' => 'staff-fixture']);
        foreach (['carbon_date_format' => 'd/m/Y', 'moment_date_format' => 'DD/MM/YYYY', 'language' => 'es', 'currency' => 1,
            'time_zone' => 'UTC', 'fiscal_year' => '1-12'] as $key => $value) {
            DB::table('company_settings')->insert(['company_id' => 1, 'option' => $key, 'value' => $value]);
        }
        foreach ([1 => 'Primario', 2 => 'Secundario'] as $level => $name) {
            DB::table('school_levels')->insert(['id' => $level, 'company_id' => 1, 'code' => 'level'.$level, 'name' => $name, 'enabled' => true]);
            $year = DB::table('academic_years')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'year' => 2026,
                'name' => 'Ciclo 2026', 'starts_on' => '2026-03-01', 'ends_on' => '2026-12-31', 'status' => 'active']);
            $grade = DB::table('grade_levels')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'name' => '1.er año', 'position' => 1]);
            foreach (['A', 'B'] as $letter) {
                $division = DB::table('divisions')->insertGetId(['company_id' => 1, 'school_level_id' => $level,
                    'academic_year_id' => $year, 'grade_level_id' => $grade, 'name' => $letter, 'enabled' => true]);
                $this->divisions[$level.$letter] = $division;
                $student = DB::table('students')->insertGetId(['company_id' => 1, 'school_level_id' => $level,
                    'first_name' => 'Alumno', 'last_name' => $level.$letter, 'school_year' => 2026, 'status' => 'active', 'dni' => 'DNI-'.$level.$letter]);
                DB::table('enrollments')->insert(['company_id' => 1, 'school_level_id' => $level, 'academic_year_id' => $year,
                    'student_id' => $student, 'division_id' => $division, 'enrolled_on' => '2026-03-01', 'status' => 'active']);
                $this->students[$level.$letter] = $student;
            }
            DB::table('subjects')->insert(['company_id' => 1, 'school_level_id' => $level, 'name' => 'Matemática '.$name]);
        }
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);
        $this->admin = $this->account('total', 'total_admin', null);
        $this->director = $this->account('director1', 'level_director', 1);
    }

    private function account(string $name, string $role, ?int $level): User
    {
        $user = User::create(['company_id' => 1, 'name' => $name, 'email' => $name.'@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'staff', 'currency_id' => 1]);
        DB::table('role_user')->insert(['company_id' => 1, 'user_id' => $user->id, 'school_level_id' => $level,
            'role_id' => DB::table('roles')->where('company_id', 1)->where('name', $role)->value('id')]);

        return $user;
    }

    private function as(User $user, ?int $level)
    {
        auth()->forgetGuards();
        $this->actingAs($user->fresh(), 'web');

        return $this->withHeaders(['company' => '1', 'school-level' => $level === null ? '' : (string) $level]);
    }

    /** La direccion carga materias por curso y da de alta un docente con acceso. */
    private function onboardTeacher(): array
    {
        $subject = DB::table('subjects')->where('school_level_id', 1)->value('id');
        $this->as($this->director, 1);
        foreach (['1A', '1B'] as $key) {
            $this->putJson('/api/v1/divisions/'.$this->divisions[$key].'/course-sections', ['subject_ids' => [$subject]])->assertOk();
        }
        $sectionA = DB::table('course_sections')->where('division_id', $this->divisions['1A'])->value('id');
        $response = $this->postJson('/api/v1/staff/onboard', [
            'first_name' => 'Docente', 'last_name' => 'Ficticia', 'email' => 'docente@example.invalid',
            'teaching' => [['course_section_id' => $sectionA, 'weekly_hours' => 4]],
            'access' => ['enabled' => true, 'password' => 'inicial-ficticia-123'],
        ])->assertStatus(201);
        $teacher = User::where('email', 'docente@example.invalid')->firstOrFail();
        $this->as($teacher, 1);
        $this->putJson('/api/v1/me/password', ['current_password' => 'inicial-ficticia-123',
            'password' => 'propia-ficticia-456', 'password_confirmation' => 'propia-ficticia-456'])->assertOk();

        return [$teacher->fresh(), $response->json(), $sectionA];
    }

    public function test_teacher_onboarding_creates_file_access_role_and_requires_password_change(): void
    {
        $subject = DB::table('subjects')->where('school_level_id', 1)->value('id');
        $this->as($this->director, 1);
        $this->putJson('/api/v1/divisions/'.$this->divisions['1A'].'/course-sections', ['subject_ids' => [$subject]])->assertOk();
        $section = DB::table('course_sections')->where('division_id', $this->divisions['1A'])->value('id');
        $file = $this->postJson('/api/v1/staff/onboard', [
            'first_name' => 'Docente', 'last_name' => 'Ficticia', 'email' => 'docente@example.invalid',
            'teaching' => [['course_section_id' => $section, 'weekly_hours' => 4, 'starts_on' => '2026-03-01']],
            'access' => ['enabled' => true, 'password' => 'inicial-ficticia-123'],
        ])->assertStatus(201)->json();

        $this->assertSame('docente@example.invalid', $file['access']['email']);
        $this->assertTrue($file['access']['must_change_password']);
        $this->assertTrue($file['teaching'][0]['granted']);
        $teacher = User::where('email', 'docente@example.invalid')->firstOrFail();
        $this->assertSame((int) $teacher->id, (int) DB::table('staff_members')->where('id', $file['member']['id'])->value('user_id'));
        $this->assertSame(1, DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('user_id', $teacher->id)->where('roles.name', 'teacher')->where('course_section_id', $section)->whereNull('revoked_at')->count());
        $this->assertContains($file['member']['id'], array_column($this->getJson('/api/v1/staff')->assertOk()->json('data'), 'id'));
        foreach (['staff.onboarded', 'staff.access_created', 'role_granted'] as $action) {
            $this->assertTrue(DB::table('audit_logs')->where('action', $action)->exists(), $action);
        }

        // Hasta cambiar la contraseña inicial, la API solo deja cambiarla.
        $this->as($teacher, 1);
        $this->getJson('/api/v1/students')->assertStatus(403)->assertJsonPath('error', 'password_change_required');
        $this->getJson('/api/v1/bootstrap')->assertOk();
        $this->putJson('/api/v1/me/password', ['current_password' => 'otra', 'password' => 'propia-ficticia-456',
            'password_confirmation' => 'propia-ficticia-456'])->assertStatus(422);
        $this->putJson('/api/v1/me/password', ['current_password' => 'inicial-ficticia-123',
            'password' => 'propia-ficticia-456', 'password_confirmation' => 'propia-ficticia-456'])->assertOk();
        $this->as($teacher, 1);
        $this->getJson('/api/v1/students')->assertOk();
        $this->assertFalse((bool) $teacher->fresh()->must_change_password);
    }

    public function test_teacher_never_reaches_accounting_users_or_settings(): void
    {
        [$teacher] = $this->onboardTeacher();
        $this->as($teacher, 1);
        $forbidden = '/^permission:(finance\.|system\.user\.|system\.role\.|system\.settings\.|system\.backup|hr\.|data\.)/';
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (strpos($route->uri(), 'api/v1/') !== 0) {
                continue;
            }
            $permissions = array_values(array_filter($route->gatherMiddleware(), fn ($m) => strpos($m, 'permission:') === 0));
            if (! $permissions || ! collect($permissions)->every(fn ($m) => preg_match($forbidden, $m))) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
                $status = $this->json($method, $uri)->status();
                $this->assertContains($status, [403, 404], $method.' '.$uri.' devolvió '.$status);
                if (strpos($route->uri(), '{') === false) {
                    $this->assertSame(403, $status, $method.' '.$uri);
                }
                $checked++;
            }
        }
        $this->assertGreaterThan(60, $checked);
        foreach (['sales/customers', 'expenses', 'profit-loss', 'tax-summary', 'sales/items'] as $report) {
            $this->get('/reports/'.$report.'/staff-fixture?from_date=2026-01-01&to_date=2026-12-31')->assertStatus(403);
        }
        $perms = $this->getJson('/api/v1/bootstrap')->assertOk()->json('user.permissions_by_level.1');
        $this->assertContains('students.view_basic', $perms);
        foreach ($perms as $permission) {
            $this->assertDoesNotMatchRegularExpression('/^(finance|system|hr|data)\./', $permission);
        }
    }

    public function test_teacher_sees_only_students_of_own_courses_and_loses_them_on_revocation(): void
    {
        [$teacher, $file] = $this->onboardTeacher();
        $this->as($teacher, 1);
        $ids = array_column($this->getJson('/api/v1/students?limit=50')->assertOk()->json('students.data'), 'id');
        $this->assertSame([$this->students['1A']], $ids);
        $this->getJson('/api/v1/students/'.$this->students['1A'])->assertOk();
        $this->getJson('/api/v1/students/'.$this->students['1B'])->assertStatus(403);
        $this->getJson('/api/v1/students', ['school-level' => '2'])->assertStatus(403);

        $this->as($this->director, 1);
        $this->postJson('/api/v1/staff/'.$file['member']['id'].'/teaching/'.$file['teaching'][0]['id'].'/revoke')->assertOk();
        $this->assertTrue(DB::table('audit_logs')->where('action', 'role_revoked')->exists());

        $this->as($teacher, 1);
        $this->getJson('/api/v1/students/'.$this->students['1A'])->assertStatus(403);
        $this->assertSame([], $this->getJson('/api/v1/students?limit=50')->json('students.data') ?? []);
    }

    public function test_primary_direction_cannot_onboard_anyone_in_secondary(): void
    {
        $role = fn ($name) => DB::table('roles')->where('company_id', 1)->where('name', $name)->value('id');
        $subject = DB::table('subjects')->where('school_level_id', 2)->value('id');
        $this->as($this->admin, 2);
        $this->putJson('/api/v1/divisions/'.$this->divisions['2A'].'/course-sections', ['subject_ids' => [$subject]])->assertOk();
        $secondarySection = DB::table('course_sections')->where('division_id', $this->divisions['2A'])->value('id');
        $base = ['first_name' => 'Persona', 'last_name' => 'Ficticia'];

        $this->as($this->director, 1);
        $this->postJson('/api/v1/staff/onboard', $base + ['cargos' => [['role_id' => $role('preceptor'),
            'school_level_id' => 2, 'division_id' => $this->divisions['2A']]]])->assertStatus(403);
        $this->postJson('/api/v1/staff/onboard', $base + ['teaching' => [['course_section_id' => $secondarySection]]])->assertStatus(403);
        $this->postJson('/api/v1/staff/onboard', $base)->assertStatus(422);
        $this->as($this->director, 2);
        $this->postJson('/api/v1/staff/onboard', $base + ['cargos' => [['role_id' => $role('preceptor'),
            'school_level_id' => 2, 'division_id' => $this->divisions['2A']]]])->assertStatus(403);
        $this->putJson('/api/v1/divisions/'.$this->divisions['2A'].'/course-sections', ['subject_ids' => []])->assertStatus(403);
        // Ni roles superiores en su propio nivel.
        $this->as($this->director, 1);
        $this->postJson('/api/v1/staff/onboard', $base + ['cargos' => [['role_id' => $role('level_director'),
            'school_level_id' => 1]]])->assertStatus(403);
        $this->assertSame(0, DB::table('staff_members')->where('first_name', 'Persona')->count());

        // En su nivel si: preceptor de 1.o A.
        $this->postJson('/api/v1/staff/onboard', $base + ['cargos' => [['role_id' => $role('preceptor'),
            'school_level_id' => 1, 'division_id' => $this->divisions['1A']]]])->assertStatus(201);
        // Recursos Humanos administra personal pero no da altas con acceso.
        $hr = $this->account('rrhh', 'hr_admin', 1);
        $this->as($hr, 1);
        $this->postJson('/api/v1/staff/onboard', $base)->assertStatus(403);
    }

    public function test_access_is_created_linked_and_disabled_from_the_file(): void
    {
        $role = DB::table('roles')->where('company_id', 1)->where('name', 'preceptor')->value('id');
        $this->as($this->director, 1);
        $file = $this->postJson('/api/v1/staff/onboard', ['first_name' => 'Pre', 'last_name' => 'Ceptor',
            'cargos' => [['role_id' => $role, 'school_level_id' => 1, 'division_id' => $this->divisions['1A']]]])->assertStatus(201)->json();
        $this->assertNull($file['access']);
        $this->assertFalse($file['cargos'][0]['granted']);
        $id = $file['member']['id'];

        $file = $this->postJson('/api/v1/staff/'.$id.'/access', ['email' => 'pre@example.invalid', 'password' => 'inicial-ficticia-123'])->assertOk()->json();
        $this->assertTrue($file['cargos'][0]['granted']);
        $user = User::where('email', 'pre@example.invalid')->firstOrFail();
        $this->assertTrue(app(\Crater\Services\Access\AccessManager::class)->allows($user, 'students.view_file', 1,
            ['type' => 'division', 'id' => $this->divisions['1A']]));

        $this->postJson('/api/v1/staff/'.$id.'/access/disable')->assertOk()->assertJsonPath('access.is_active', false);
        $this->assertFalse((bool) $user->fresh()->is_active);
        $this->postJson('/api/v1/staff/'.$id.'/cargos/'.$file['cargos'][0]['id'].'/end')->assertOk();
        $this->assertNotNull(DB::table('role_user')->where('user_id', $user->id)->value('revoked_at'));
        $this->assertTrue(DB::table('audit_logs')->where('action', 'staff.access_disabled')->exists());

        // Vincular una cuenta existente de otra ficha se rechaza.
        $other = $this->postJson('/api/v1/staff/onboard', ['first_name' => 'Otra', 'last_name' => 'Persona',
            'cargos' => [['role_id' => $role, 'school_level_id' => 1, 'division_id' => $this->divisions['1B']]]])->json('member.id');
        $this->postJson('/api/v1/staff/'.$other.'/access', ['user_id' => $user->id])->assertStatus(422);
    }

    public function test_migration_links_files_and_users_by_email_and_creates_missing_files(): void
    {
        $linked = $this->account('coincide', 'staff', 1);
        $alone = $this->account('sinficha', 'staff', 1);
        $family = User::create(['company_id' => 1, 'name' => 'Familia', 'email' => 'familia@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'customer', 'currency_id' => 1]);
        $member = DB::table('staff_members')->insertGetId(['company_id' => 1, 'first_name' => 'Coin', 'last_name' => 'Cide',
            'email' => 'COINCIDE@example.invalid', 'staff_category' => 'other', 'employment_status' => 'active']);
        DB::table('staff_members')->whereNotNull('user_id')->delete();

        (new \CreateStaffOnboarding())->up();

        $this->assertSame((int) $linked->id, (int) DB::table('staff_members')->where('id', $member)->value('user_id'));
        $this->assertSame(1, DB::table('staff_members')->where('user_id', $alone->id)->count());
        $this->assertSame(0, DB::table('staff_members')->where('user_id', $family->id)->count());
        $this->assertSame(1, DB::table('staff_members')->where('user_id', $linked->id)->count());
    }
}
