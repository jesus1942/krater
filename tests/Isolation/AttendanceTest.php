<?php

namespace Tests\Isolation;

use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** Asistencia: toma diaria (Primario) y por materia (Terciario), personal, licencias y panel de la direccion. */
class AttendanceTest extends TestCase
{
    use CreatesApplication;

    private array $divisions = [];
    private array $enrollments = [];
    private array $sections = [];
    private array $members = [];
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'queue.default' => 'sync', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite'); TenantContext::clear();
        Artisan::call('migrate', ['--force' => true]);
        // Misma correccion de PK de SQLite que WholeInstitutionTest: las reglas del nivel se guardan en company_settings.
        $sql = DB::selectOne('SELECT sql FROM sqlite_master WHERE name = ?', ['company_settings'])->sql;
        if (strpos($sql, 'PRIMARY KEY(') !== false) {
            $sql = preg_replace('/, PRIMARY KEY\([^)]*\)/', '', $sql);
            $sql = preg_replace('/\bid INTEGER NOT NULL\b/', 'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT', $sql, 1);
            DB::statement('DROP TABLE company_settings'); DB::statement($sql);
        }
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        DB::table('companies')->insert(['id' => 1, 'name' => 'Institucion ficticia', 'unique_hash' => 'attendance-fixture']);
        foreach (['carbon_date_format' => 'd/m/Y', 'moment_date_format' => 'DD/MM/YYYY', 'language' => 'es', 'currency' => 1,
            'time_zone' => 'UTC', 'fiscal_year' => '1-12'] as $key => $value) {
            DB::table('company_settings')->insert(['id' => DB::table('company_settings')->max('id') + 1, 'company_id' => 1, 'option' => $key, 'value' => $value]);
        }
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);

        foreach ([1 => ['primary', 'Primario'], 3 => ['tertiary', 'Terciario']] as $level => [$code, $name]) {
            DB::table('school_levels')->insert(['id' => $level, 'company_id' => 1, 'code' => $code, 'name' => $name, 'enabled' => true]);
            $year = DB::table('academic_years')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'year' => now()->year,
                'name' => 'Ciclo', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString(), 'status' => 'active']);
            $grade = DB::table('grade_levels')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'name' => '1.er año', 'position' => 1]);
            $subject = DB::table('subjects')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'name' => 'Física '.$name]);
            foreach (['A', 'B'] as $letter) {
                $division = DB::table('divisions')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'academic_year_id' => $year,
                    'grade_level_id' => $grade, 'name' => $letter, 'enabled' => true]);
                $this->divisions[$level.$letter] = $division;
                $this->sections[$level.$letter] = DB::table('course_sections')->insertGetId(['company_id' => 1, 'school_level_id' => $level,
                    'academic_year_id' => $year, 'division_id' => $division, 'subject_id' => $subject, 'status' => 'active']);
                foreach ([1, 2] as $n) {
                    $student = DB::table('students')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'first_name' => 'Alumno '.$n,
                        'last_name' => $name.$letter, 'school_year' => now()->year, 'status' => 'active', 'dni' => 'DNI-'.$level.$letter.$n]);
                    $this->enrollments[$level.$letter.$n] = DB::table('enrollments')->insertGetId(['company_id' => 1, 'school_level_id' => $level,
                        'academic_year_id' => $year, 'student_id' => $student, 'division_id' => $division,
                        'enrolled_on' => now()->startOfYear()->toDateString(), 'status' => 'active']);
                }
            }
        }
        $this->admin = $this->account('total', 'total_admin', null);
    }

    private function account(string $name, string $role, ?int $level, array $scope = []): User
    {
        $user = User::create(['company_id' => 1, 'name' => $name, 'email' => $name.'@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'staff', 'currency_id' => 1]);
        DB::table('role_user')->insert(['company_id' => 1, 'user_id' => $user->id, 'school_level_id' => $level, 'managed_scope' => (bool) $scope,
            'role_id' => DB::table('roles')->where('company_id', 1)->where('name', $role)->value('id')] + $scope);

        return $user;
    }

    private function member(string $name, int $level, ?int $section = null): int
    {
        $id = DB::table('staff_members')->insertGetId(['company_id' => 1, 'first_name' => $name, 'last_name' => 'Ficticia',
            'staff_category' => 'teacher', 'employment_status' => 'active']);
        if ($section) {
            DB::table('teaching_assignments')->insert(['company_id' => 1, 'school_level_id' => $level, 'staff_member_id' => $id,
                'course_section_id' => $section, 'starts_on' => now()->startOfYear()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('staff_assignments')->insert(['company_id' => 1, 'staff_member_id' => $id, 'school_level_id' => $level,
                'position_title' => 'Preceptor', 'function_category' => 'support', 'active' => true]);
        }

        return $this->members[$name] = $id;
    }

    private function as(User $user, ?int $level)
    {
        auth()->forgetGuards();
        TenantContext::clear();
        $this->actingAs($user->fresh(), 'web');

        return $this->withHeaders(['company' => '1', 'school-level' => $level === null ? '' : (string) $level]);
    }

    private function take(string $type, int $id, string $date, array $statuses)
    {
        return $this->putJson('/api/v1/attendance/sheet', ['type' => $type, 'id' => $id, 'date' => $date,
            'records' => collect($statuses)->map(fn ($status, $enrollment) => ['enrollment_id' => $enrollment, 'status' => $status])->values()->all()]);
    }

    public function test_preceptor_takes_the_daily_list_of_its_division_only(): void
    {
        $preceptor = $this->account('preceptor', 'preceptor', 1, ['division_id' => $this->divisions['1A']]);
        $this->as($preceptor, 1);
        $targets = $this->getJson('/api/v1/attendance/targets')->assertOk()->json();
        $this->assertSame('daily', $targets['mode']);
        $this->assertSame([$this->divisions['1A']], array_column($targets['targets'], 'id'));

        $today = now()->toDateString();
        $sheet = $this->take('division', $this->divisions['1A'], $today, [$this->enrollments['1A1'] => 'present', $this->enrollments['1A2'] => 'absent'])
            ->assertOk()->json();
        $this->assertTrue($sheet['taken']);
        $this->assertSame(['present', 'absent'], array_column($sheet['students'], 'status'));
        $this->assertSame(1.0, (float) DB::table('attendance_records')->where('enrollment_id', $this->enrollments['1A2'])->value('absence_weight'));
        // Corregir el mismo dia actualiza, no duplica.
        $this->take('division', $this->divisions['1A'], $today, [$this->enrollments['1A2'] => 'late'])->assertOk();
        $this->assertSame(2, DB::table('attendance_records')->whereNull('course_section_id')->count());
        $this->assertSame(0.5, (float) DB::table('attendance_records')->where('enrollment_id', $this->enrollments['1A2'])->value('absence_weight'));

        $this->take('division', $this->divisions['1B'], $today, [$this->enrollments['1B1'] => 'present'])->assertStatus(403);
        $this->take('division', $this->divisions['1A'], $today, [$this->enrollments['1B1'] => 'present'])->assertStatus(422);
        $this->take('division', $this->divisions['1A'], now()->addDay()->toDateString(), [$this->enrollments['1A1'] => 'present'])->assertStatus(422);
        $this->take('course_section', $this->sections['1A'], $today, [$this->enrollments['1A1'] => 'present'])->assertStatus(422);
        // Dias viejos: el preceptor no; la direccion si (queda auditado como grave).
        $old = now()->subDays(6)->toDateString();
        $this->take('division', $this->divisions['1A'], $old, [$this->enrollments['1A1'] => 'absent'])->assertStatus(403);
        $this->as($this->account('directora', 'level_director', 1), 1);
        $this->take('division', $this->divisions['1A'], $old, [$this->enrollments['1A1'] => 'absent'])->assertOk();
        $this->assertTrue(DB::table('audit_logs')->where('action', 'attendance.recorded')->where('severity', 'high')->exists());
    }

    public function test_primary_teacher_takes_the_daily_list_where_she_teaches_and_tertiary_goes_by_subject(): void
    {
        $maestra = $this->account('maestra', 'teacher', 1, ['course_section_id' => $this->sections['1B']]);
        $this->as($maestra, 1);
        $this->assertSame([$this->divisions['1B']], array_column($this->getJson('/api/v1/attendance/targets')->json('targets'), 'id'));
        $this->take('division', $this->divisions['1B'], now()->toDateString(), [$this->enrollments['1B1'] => 'absent'])->assertOk();

        $docente = $this->account('docente', 'teacher', 3, ['course_section_id' => $this->sections['3A']]);
        $this->as($docente, 3);
        $targets = $this->getJson('/api/v1/attendance/targets')->assertOk()->json();
        $this->assertSame(['subject', [$this->sections['3A']]], [$targets['mode'], array_column($targets['targets'], 'id')]);
        $this->take('division', $this->divisions['3A'], now()->toDateString(), [$this->enrollments['3A1'] => 'present'])->assertStatus(422);
        $this->take('course_section', $this->sections['3A'], now()->toDateString(), [$this->enrollments['3A1'] => 'present', $this->enrollments['3A2'] => 'absent'])->assertOk();
        $this->take('course_section', $this->sections['3B'], now()->toDateString(), [$this->enrollments['3B1'] => 'present'])->assertStatus(403);
        $this->getJson('/api/v1/attendance/panel')->assertStatus(403);
    }

    public function test_justification_needs_permission_and_an_absence(): void
    {
        $preceptor = $this->account('preceptor', 'preceptor', 1, ['division_id' => $this->divisions['1A']]);
        $this->as($preceptor, 1);
        $this->take('division', $this->divisions['1A'], now()->toDateString(), [$this->enrollments['1A1'] => 'present', $this->enrollments['1A2'] => 'absent'])->assertOk();
        $absent = DB::table('attendance_records')->where('enrollment_id', $this->enrollments['1A2'])->value('id');
        $present = DB::table('attendance_records')->where('enrollment_id', $this->enrollments['1A1'])->value('id');
        $this->postJson('/api/v1/attendance/records/'.$absent.'/justify', ['justification' => 'Certificado médico'])->assertOk()
            ->assertJsonPath('is_justified', true);
        $this->postJson('/api/v1/attendance/records/'.$present.'/justify', ['justification' => 'x'])->assertStatus(422);
        $this->as($this->account('maestra', 'teacher', 1, ['course_section_id' => $this->sections['1A']]), 1);
        $this->postJson('/api/v1/attendance/records/'.$absent.'/justify', ['justification' => null])->assertStatus(403);
    }

    public function test_staff_attendance_and_leaves_by_level(): void
    {
        $preceptora = $this->member('Preceptora', 1);
        $profe = $this->member('Profe terciario', 3, $this->sections['3A']);
        $directora = $this->account('directora', 'level_director', 1);
        $this->as($directora, 1);
        $day = $this->getJson('/api/v1/staff-attendance?date='.now()->toDateString())->assertOk()->json();
        $this->assertSame([$preceptora], array_column($day['members'], 'staff_member_id'));
        $this->putJson('/api/v1/staff-attendance', ['date' => now()->toDateString(), 'records' => [['staff_member_id' => $preceptora, 'status' => 'late', 'notes' => '20 minutos']]])
            ->assertOk()->assertJsonPath('members.0.status', 'late');
        $this->putJson('/api/v1/staff-attendance', ['date' => now()->toDateString(), 'records' => [['staff_member_id' => $profe, 'status' => 'absent']]])->assertStatus(403);

        $leave = $this->postJson('/api/v1/staff-leaves', ['staff_member_id' => $preceptora, 'type' => 'illness',
            'starts_on' => now()->toDateString(), 'ends_on' => now()->addDays(3)->toDateString()])->assertStatus(201)->json();
        $this->postJson('/api/v1/staff-leaves', ['staff_member_id' => $preceptora, 'type' => 'personal',
            'starts_on' => now()->addDays(2)->toDateString(), 'ends_on' => now()->addDays(5)->toDateString()])->assertStatus(422);
        $this->postJson('/api/v1/staff-leaves', ['staff_member_id' => $profe, 'type' => 'illness',
            'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString()])->assertStatus(403);
        $this->assertSame(['leave', 'Enfermedad'], [$this->getJson('/api/v1/staff-attendance?date='.now()->toDateString())->json('members.0.status'),
            $this->getJson('/api/v1/staff-attendance?date='.now()->toDateString())->json('members.0.leave.type_label')]);
        $this->postJson('/api/v1/staff-leaves/'.$leave['id'].'/cancel')->assertOk()->assertJsonPath('cancelled', true);
        $this->assertSame('late', $this->getJson('/api/v1/staff-attendance?date='.now()->toDateString())->json('members.0.status'));

        // El personal de apoyo y los preceptores no ven la asistencia del personal.
        $this->as($this->account('preceptor', 'preceptor', 1, ['division_id' => $this->divisions['1A']]), 1);
        $this->getJson('/api/v1/staff-attendance?date='.now()->toDateString())->assertStatus(403);
    }

    public function test_direction_panel_shows_today_percentages_risk_and_staff_absenteeism(): void
    {
        $profe = $this->member('Profe', 1, $this->sections['1A']);
        $preceptora = $this->member('Preceptora', 1);
        $today = now()->toDateString();
        // 1.o A: el alumno 2 acumula 21 inasistencias en el ciclo (riesgo: 80 % de 25).
        foreach (range(1, 21) as $i) {
            DB::table('attendance_records')->insert(['company_id' => 1, 'school_level_id' => 1, 'academic_year_id' => DB::table('divisions')->where('id', $this->divisions['1A'])->value('academic_year_id'),
                'enrollment_id' => $this->enrollments['1A2'], 'division_id' => $this->divisions['1A'], 'date' => now()->startOfYear()->addDays($i)->toDateString(),
                'status' => 'absent', 'absence_weight' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $directora = $this->account('directora', 'level_director', 1);
        $this->as($directora, 1);
        $this->take('division', $this->divisions['1A'], $today, [$this->enrollments['1A1'] => 'present', $this->enrollments['1A2'] => 'absent'])->assertOk();
        $this->putJson('/api/v1/staff-attendance', ['date' => $today, 'records' => [['staff_member_id' => $profe, 'status' => 'absent']]])->assertOk();
        $this->postJson('/api/v1/staff-leaves', ['staff_member_id' => $preceptora, 'type' => 'training', 'starts_on' => $today, 'ends_on' => $today])->assertStatus(201);

        $panel = $this->getJson('/api/v1/attendance/panel?date='.$today)->assertOk()->json();
        $this->assertSame([1, 1], [$panel['today']['students']['absent_total'], $panel['today']['students']['pending']]);
        $this->assertSame([true, false], array_column($panel['today']['students']['divisions'], 'taken'));
        $this->assertSame([['Profe Ficticia'], 1], [array_map(fn ($p) => $p['name'] === 'Ficticia, Profe' ? 'Profe Ficticia' : $p['name'], $panel['today']['staff']['absent']), count($panel['today']['staff']['leave'])]);
        $this->assertSame([$this->sections['1A']], array_column($panel['today']['uncovered_sections'], 'course_section_id'));
        $this->assertSame('warning', $panel['at_risk']['students'][0]['level']);
        $this->assertEquals(22, $panel['at_risk']['students'][0]['absences']);
        $this->assertNotNull($panel['by_division']['divisions'][0]['year']);
        $this->assertSame(['absent_days' => 1, 'leave_days' => now()->isWeekend() ? 0 : 1],
            ['absent_days' => $panel['staff_absenteeism']['absent_days'], 'leave_days' => $panel['staff_absenteeism']['leave_days']]);

        // La direccion ajusta el limite del nivel; con 22 inasistencias y tope 20 queda excedido.
        $this->putJson('/api/v1/attendance/rules', ['mode' => 'daily', 'max_absences' => 20, 'min_percentage' => 75])->assertOk();
        $this->assertSame('exceeded', $this->getJson('/api/v1/attendance/panel?date='.$today)->json('at_risk.students.0.level'));

        // Preceptor (alcance de division) y tesoreria no ven el panel.
        $preceptor = $this->account('preceptor', 'preceptor', 1, ['division_id' => $this->divisions['1A']]);
        $this->as($preceptor, 1);
        $this->getJson('/api/v1/attendance/panel')->assertStatus(403);
        $this->putJson('/api/v1/attendance/rules', ['mode' => 'subject', 'max_absences' => 5, 'min_percentage' => 50])->assertStatus(403);
        $this->as($this->account('tesoreria', 'finance_admin', 1), 1);
        $this->getJson('/api/v1/attendance/panel')->assertStatus(403);
    }

    public function test_tertiary_panel_flags_students_below_the_subject_percentage(): void
    {
        $year = DB::table('divisions')->where('id', $this->divisions['3A'])->value('academic_year_id');
        foreach (range(1, 8) as $i) {
            DB::table('attendance_records')->insert(['company_id' => 1, 'school_level_id' => 3, 'academic_year_id' => $year,
                'enrollment_id' => $this->enrollments['3A1'], 'division_id' => $this->divisions['3A'], 'course_section_id' => $this->sections['3A'],
                'date' => now()->startOfYear()->addDays($i)->toDateString(), 'status' => $i <= 3 ? 'absent' : 'present',
                'absence_weight' => $i <= 3 ? 1 : 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->as($this->admin, 3);
        $risk = $this->getJson('/api/v1/attendance/panel')->assertOk()->json('at_risk');
        $this->assertEquals(['subject', 75], [$risk['mode'], $risk['limit']]);
        $this->assertEquals([62.5, 'exceeded', 'Física Terciario'], [$risk['students'][0]['percentage'], $risk['students'][0]['level'], $risk['students'][0]['subject']]);
    }
}
