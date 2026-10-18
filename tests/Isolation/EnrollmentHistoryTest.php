<?php

namespace Tests\Isolation;

use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** Fila 7: correccion de carga vs pase, historial visible y legacy derivado. */
class EnrollmentHistoryTest extends TestCase
{
    use CreatesApplication;

    private User $admin;
    private array $years = [];
    private array $grades = [];
    private array $divisions = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'queue.default' => 'sync', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite'); TenantContext::clear();
        Artisan::call('migrate', ['--force' => true]);
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
        DB::table('companies')->insert(['id' => 1, 'name' => 'Institucion ficticia', 'unique_hash' => 'history-fixture']);
        foreach (['carbon_date_format' => 'd/m/Y', 'moment_date_format' => 'DD/MM/YYYY', 'language' => 'es', 'currency' => 1,
            'time_zone' => 'UTC', 'fiscal_year' => '1-12'] as $key => $value) {
            DB::table('company_settings')->insert(['company_id' => 1, 'option' => $key, 'value' => $value]);
        }
        foreach ([1 => ['primary', 'Primario'], 2 => ['secondary', 'Secundario']] as $level => [$code, $name]) {
            DB::table('school_levels')->insert(['id' => $level, 'company_id' => 1, 'code' => $code, 'name' => $name, 'enabled' => true]);
            $this->years[$level] = DB::table('academic_years')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'year' => 2026,
                'name' => 'Ciclo 2026', 'starts_on' => '2026-03-01', 'ends_on' => '2026-12-31', 'status' => 'active']);
            $this->grades[$level] = DB::table('grade_levels')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'name' => '1.er año', 'position' => 1]);
            foreach (['A', 'B'] as $letter) {
                $this->divisions[$level.$letter] = DB::table('divisions')->insertGetId(['company_id' => 1, 'school_level_id' => $level,
                    'academic_year_id' => $this->years[$level], 'grade_level_id' => $this->grades[$level], 'name' => $letter, 'enabled' => true]);
            }
        }
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);
        $this->admin = $this->account('total', 'total_admin', null);
    }

    private function account(string $name, string $role, ?int $level, ?int $division = null): User
    {
        $user = User::create(['company_id' => 1, 'name' => $name, 'email' => $name.'@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'staff', 'currency_id' => 1]);
        DB::table('role_user')->insert(['company_id' => 1, 'user_id' => $user->id, 'school_level_id' => $level,
            'division_id' => $division, 'managed_scope' => $division !== null,
            'role_id' => DB::table('roles')->where('company_id', 1)->where('name', $role)->value('id')]);

        return $user;
    }

    private function as(User $user, ?int $level)
    {
        auth()->forgetGuards();
        $this->actingAs($user->fresh(), 'web');

        return $this->withHeaders(['company' => '1', 'school-level' => $level === null ? '' : (string) $level]);
    }

    private function place(string $division, string $last = 'Ficticio'): int
    {
        $level = (int) substr($division, 0, 1);
        $this->as($this->admin, $level);

        return $this->postJson('/api/v1/students', ['first_name' => 'Alumno', 'last_name' => $last, 'status' => 'active',
            'school_level_id' => $level, 'academic_year_id' => $this->years[$level], 'grade_level_id' => $this->grades[$level],
            'division_id' => $this->divisions[$division]])->assertStatus(201)->json('student.id');
    }

    private function relocate(int $student, string $division, string $mode, int $status = 200, array $extra = [])
    {
        $level = (int) substr($division, 0, 1);

        return $this->putJson('/api/v1/students/'.$student.'/relocate', $extra + ['mode' => $mode, 'school_level_id' => $level,
            'academic_year_id' => $this->years[$level], 'grade_level_id' => $this->grades[$level],
            'division_id' => $this->divisions[$division], 'reason' => 'Motivo ficticio'])->assertStatus($status);
    }

    private function enrollments(int $student)
    {
        return DB::table('enrollments')->where('student_id', $student)->orderBy('id')->get();
    }

    public function test_direction_created_student_gets_an_enrollment_and_editing_does_not_move_it(): void
    {
        $id = $this->place('1A');
        $rows = $this->enrollments($id);
        $this->assertCount(1, $rows);
        $this->assertSame([(int) $this->divisions['1A'], 'active'], [(int) $rows[0]->division_id, $rows[0]->status]);
        $student = DB::table('students')->find($id);
        $this->assertSame(['Primario', '1.er año', 'A', 2026], [$student->level, $student->grade, $student->division, (int) $student->school_year]);

        $same = ['first_name' => 'Editado', 'last_name' => 'Ficticio', 'status' => 'active', 'school_level_id' => 1,
            'academic_year_id' => $this->years[1], 'grade_level_id' => $this->grades[1], 'division_id' => $this->divisions['1A']];
        $this->putJson('/api/v1/students/'.$id, $same)->assertOk();
        $this->putJson('/api/v1/students/'.$id, ['division_id' => $this->divisions['1B']] + $same)->assertStatus(422);
        // Los campos legacy que mande el cliente no se escriben.
        $this->putJson('/api/v1/students/'.$id, ['first_name' => 'Otra', 'last_name' => 'Vez', 'status' => 'active',
            'level' => 'Inventado', 'grade' => 'X', 'division' => 'Z', 'school_year' => 1999])->assertOk();
        $this->assertSame(['Primario', 'A', 2026], [DB::table('students')->find($id)->level, DB::table('students')->find($id)->division,
            (int) DB::table('students')->find($id)->school_year]);
        $this->assertCount(1, $this->enrollments($id));
        $this->assertSame((int) $this->divisions['1A'], (int) $this->enrollments($id)[0]->division_id);
    }

    public function test_correction_fixes_the_current_enrollment_and_transfer_closes_it_and_opens_another(): void
    {
        $id = $this->place('1A');
        $original = $this->enrollments($id)[0]->id;
        $this->relocate($id, '1B', 'correction', 422, ['mode' => null]);

        $this->relocate($id, '1B', 'correction');
        $rows = $this->enrollments($id);
        $this->assertCount(1, $rows);
        $this->assertSame([(int) $original, (int) $this->divisions['1B'], 'active'], [(int) $rows[0]->id, (int) $rows[0]->division_id, $rows[0]->status]);

        $on = now()->addDay()->toDateString();
        $this->relocate($id, '1A', 'transfer', 422, ['effective_on' => now()->subYear()->toDateString()]);
        $this->relocate($id, '1A', 'transfer', 200, ['effective_on' => $on]);
        $rows = $this->enrollments($id);
        $this->assertCount(2, $rows);
        $this->assertSame(['transferred_out', $on], [$rows[0]->status, substr($rows[0]->left_on, 0, 10)]);
        $this->assertSame(['active', (int) $rows[0]->id, $on], [$rows[1]->status, (int) $rows[1]->previous_enrollment_id, substr($rows[1]->enrolled_on, 0, 10)]);
        $this->assertSame('A', DB::table('students')->find($id)->division);
        $modes = DB::table('audit_logs')->where('action', 'student_relocated')->where('auditable_id', $id)->pluck('new_values')
            ->map(fn ($v) => json_decode($v, true)['mode'])->all();
        $this->assertSame(['correction', 'transfer'], $modes);

        // Un pase va a otra division y necesita una matricula vigente.
        $this->relocate($id, '1A', 'transfer', 422);
        $other = $this->place('1B', 'Otro');
        DB::table('enrollments')->where('student_id', $other)->update(['status' => 'withdrawn']);
        $this->relocate($other, '1A', 'transfer', 422);
    }

    public function test_primary_to_secondary_transfer_keeps_history_visible_and_passes_the_smoke(): void
    {
        $id = $this->place('1A');
        $preceptor1 = $this->account('pre1', 'preceptor', 1, $this->divisions['1A']);
        $preceptor2 = $this->account('pre2', 'preceptor', 2, $this->divisions['2A']);
        $this->assertSame(0, Artisan::call('ena:smoke'));

        $this->as($this->admin, null);
        $this->relocate($id, '2A', 'transfer');
        $this->assertSame(0, Artisan::call('ena:smoke'), Artisan::output());
        $student = DB::table('students')->find($id);
        $this->assertSame([2, 'Secundario'], [(int) $student->school_level_id, $student->level]);

        // Historial completo desde Secundario, incluida la matricula de Primario.
        $this->as($preceptor2, 2);
        $history = $this->getJson('/api/v1/students/'.$id.'/enrollments')->assertOk()->json('data');
        $this->assertSame([['Primario', 'transferred_out'], ['Secundario', 'active']],
            array_map(fn ($row) => [$row['school_level'], $row['status']], $history));
        $this->assertContains($id, array_column($this->getJson('/api/v1/students?limit=50')->json('students.data'), 'id'));

        // En Primario la matricula historica sigue mostrando al alumno; ya no es suyo.
        $this->as($preceptor1, 1);
        $rows = $this->getJson('/api/v1/enrollments?division_id='.$this->divisions['1A'])->assertOk()->json('data');
        $this->assertSame([['transferred_out', 'Ficticio']], array_map(fn ($row) => [$row['status'], $row['student']['last_name'] ?? null], $rows));
        $this->assertNotContains($id, array_column($this->getJson('/api/v1/students?limit=50')->json('students.data') ?? [], 'id'));
        // Para Primario el alumno ya no existe: ni su ficha ni su historial.
        $this->getJson('/api/v1/students/'.$id.'/enrollments')->assertStatus(404);
        $this->getJson('/api/v1/students/'.$id)->assertStatus(404);
        $this->assertSame(0, DB::table('enrollments')->leftJoin('students', 'students.id', '=', 'enrollments.student_id')->whereNull('students.id')->count());
    }

    public function test_siblings_in_different_levels_are_relocated_independently(): void
    {
        $older = $this->place('2A', 'Hermano');
        $younger = $this->place('1A', 'Hermano');
        $family = DB::table('family_members')->insertGetId(['company_id' => 1, 'name' => 'Familia ficticia', 'dni' => 'FAM-1',
            'created_at' => now(), 'updated_at' => now()]);
        foreach ([$older, $younger] as $sibling) {
            DB::table('student_family_members')->insert(['company_id' => 1, 'student_id' => $sibling, 'family_member_id' => $family,
                'is_financial_responsible' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $before = $this->enrollments($older);
        $this->as($this->admin, null);
        $this->relocate($younger, '1B', 'transfer');
        // Volver a una division anterior del mismo ciclo, por correccion o por pase.
        $this->relocate($younger, '1A', 'correction');
        $this->relocate($younger, '1B', 'transfer');
        $this->assertSame(1, DB::table('enrollments')->where('student_id', $younger)->where('status', 'active')->count());

        $this->assertEquals($before, $this->enrollments($older));
        $this->assertSame(['Secundario', 'A'], [DB::table('students')->find($older)->level, DB::table('students')->find($older)->division]);
        $this->assertSame(2, DB::table('student_family_members')->where('family_member_id', $family)->count());
    }

    public function test_division_capacity_applies_to_correction_and_transfer(): void
    {
        DB::table('divisions')->where('id', $this->divisions['1B'])->update(['capacity' => 1]);
        $this->place('1B', 'Ocupa');
        $id = $this->place('1A');
        $this->relocate($id, '1B', 'correction', 422);
        $this->relocate($id, '1B', 'transfer', 422);
        $rows = $this->enrollments($id);
        $this->assertSame([[(int) $this->divisions['1A'], 'active']], $rows->map(fn ($r) => [(int) $r->division_id, $r->status])->all());
    }

    public function test_migration_creates_enrollments_only_for_exact_legacy_matches(): void
    {
        $base = ['company_id' => 1, 'first_name' => 'Legacy', 'status' => 'active', 'school_year' => 2026];
        $exact = DB::table('students')->insertGetId($base + ['last_name' => 'Exacto', 'school_level_id' => 1, 'level' => 'Primario',
            'grade' => '1.er año', 'division' => 'B', 'dni' => 'L-1']);
        $missing = DB::table('students')->insertGetId($base + ['last_name' => 'Sin', 'school_level_id' => 1, 'level' => 'Primario',
            'grade' => '1.er año', 'division' => 'Z', 'dni' => 'L-2']);
        $kept = $this->place('2A', 'Matriculado');

        (new \EnrollmentHistory())->up();
        (new \EnrollmentHistory())->up(); // repetible

        $this->assertSame([[(int) $this->divisions['1B'], 'active']], $this->enrollments($exact)->map(fn ($r) => [(int) $r->division_id, $r->status])->all());
        $this->assertCount(0, $this->enrollments($missing));
        $this->assertCount(1, $this->enrollments($kept));
    }
}
