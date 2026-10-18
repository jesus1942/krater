<?php

namespace Tests\Support;

use Crater\Models\AcademicYear;
use Crater\Models\Division;
use Crater\Models\FamilyMember;
use Crater\Models\GradeLevel;
use Crater\Models\SchoolLevel;
use Crater\Models\Student;
use Illuminate\Support\Facades\DB;

/** Mismo recorrido HTTP en SQLite local y MySQL de staging. Solo datos ficticios. */
class InstitutionScenario
{
    /** Prepara tres niveles y vínculos financieros, sin tocar registros anteriores. */
    public static function fixture(int $company, int $customer): array
    {
        $rows = [];
        foreach (['primary' => 'Primario', 'secondary' => 'Secundario', 'tertiary' => 'Terciario'] as $code => $label) {
            $level = SchoolLevel::unguarded(fn () => SchoolLevel::firstOrCreate(['company_id' => $company, 'code' => $code], ['name' => $label.' (prueba)', 'enabled' => true]));
            $year = AcademicYear::firstOrCreate(['company_id' => $company, 'school_level_id' => $level->id, 'year' => 2026],
                ['name' => 'Ciclo ficticio 2026', 'starts_on' => '2026-03-01', 'ends_on' => '2026-12-31', 'status' => 'active']);
            $grade = GradeLevel::firstOrCreate(['company_id' => $company, 'school_level_id' => $level->id, 'name' => 'Curso institución ficticio'], ['position' => 1, 'enabled' => true]);
            $division = Division::firstOrCreate(['company_id' => $company, 'school_level_id' => $level->id,
                'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Institución prueba'], ['enabled' => true]);
            $student = Student::firstOrCreate(['company_id' => $company, 'school_level_id' => $level->id,
                'dni' => 'INSTITUCION-PRUEBA-'.$level->id], ['first_name' => 'Alumno ficticio', 'last_name' => $label,
                'school_year' => 2026, 'grade' => $grade->name, 'division' => $division->name, 'status' => 'active']);
            $member = FamilyMember::firstOrCreate(['company_id' => $company, 'user_id' => $customer],
                ['dni' => 'FAMILIA-INSTITUCION-PRUEBA-'.$customer, 'name' => 'Familia ficticia '.$label, 'user_id' => $customer]);
            $student->familyMembers()->syncWithoutDetaching([$member->id => ['company_id' => $company, 'is_financial_responsible' => true]]);
            $rows[] = ['school_level_id' => $level->id, 'academic_year_id' => $year->id,
                'grade_level_id' => $grade->id, 'division_id' => $division->id, 'student_id' => $student->id,
                'family_member_id' => $member->id, 'user_id' => $customer];
        }
        $category = DB::table('expense_categories')->where('company_id', $company)->value('id');
        if (! $category) $category = DB::table('expense_categories')->insertGetId(['company_id' => $company, 'name' => 'Gasto ficticio institución', 'created_at' => now(), 'updated_at' => now()]);
        return ['levels' => $rows, 'category' => (int) $category];
    }

    /** Cada pedido positivo exige su estado exacto: cualquier 403 hace fallar el recorrido. */
    public static function run(callable $request, array $fixture, int $company, string $hash): array
    {
        $run = strtoupper(bin2hex(random_bytes(5)));
        $checks = [];
        $call = function ($method, $path, $payload = [], $level = null, $expected = 200) use ($request, &$checks) {
            $response = $request($method, $path, $payload, $level);
            $checks[] = ['method' => $method, 'path' => $path, 'level' => $level, 'expected' => $expected, 'actual' => $response['status']];
            if ($response['status'] !== $expected) throw new \RuntimeException($method.' '.$path.' devolvió '.$response['status'].'; se esperaba '.$expected);
            return $response['json'] ?? [];
        };
        // Pantallas y auxiliares reales, iguales con nivel activo y en Toda la institución.
        $screens = ['bootstrap', 'me', 'school-levels', 'dashboard', 'customers', 'items', 'units',
            'invoices', 'invoices/templates', 'estimates', 'estimates/templates', 'payments', 'payment-methods',
            'expenses', 'categories', 'students', 'academic-years', 'grade-levels', 'divisions', 'subjects',
            'study-plans', 'school-billing/options', 'users', 'roles', 'company/settings?settings%5B0%5D=currency', 'backups'];
        foreach ([null, ...array_column($fixture['levels'], 'school_level_id')] as $level) {
            foreach ($screens as $screen) $call('GET', '/api/v1/'.$screen, [], $level);
            foreach ($fixture['levels'] as $row) {
                if ($level !== null && $row['school_level_id'] !== $level) continue;
                $call('GET', '/api/v1/students/placement-options?school_level_id='.$row['school_level_id'], [], $level);
                $call('GET', '/api/v1/enrollments?division_id='.$row['division_id'], [], $level);
            }
        }
        $records = [];
        foreach ($fixture['levels'] as $index => $row) {
            $idLevel = (int) $row['school_level_id'];
            $item = ['name' => 'Concepto ficticio '.$run.' '.$index, 'price' => 10000, 'school_level_id' => $idLevel];
            $itemId = $call('POST', '/api/v1/items', $item)['item']['id'];
            $line = ['item_id' => $itemId, 'name' => $item['name'], 'price' => 10000, 'quantity' => 1,
                'total' => 10000, 'tax' => 0, 'discount' => 0, 'discount_val' => 0, 'discount_type' => 'fixed'];
            $base = ['school_level_id' => $idLevel, 'user_id' => $row['user_id'], 'discount' => 0, 'discount_val' => 0,
                'discount_type' => 'fixed', 'sub_total' => 10000, 'total' => 10000, 'tax' => 0, 'template_name' => 'invoice1', 'items' => [$line]];
            $invoice = $base + ['student_id' => $row['student_id'], 'family_member_id' => $row['family_member_id'],
                'invoice_number' => 'INST-'.$run.'-'.$index, 'invoice_date' => '2026-10-02', 'due_date' => '2026-10-30'];
            $invoiceId = $call('POST', '/api/v1/invoices', $invoice)['invoice']['id'];
            $estimate = $base + ['estimate_number' => 'ESTINST-'.$run.'-'.$index, 'estimate_date' => '2026-10-02', 'expiry_date' => '2026-10-30'];
            $estimate['template_name'] = 'estimate1';
            $estimateId = $call('POST', '/api/v1/estimates', $estimate)['estimate']['id'];
            $payment = ['school_level_id' => $idLevel, 'invoice_id' => $invoiceId, 'user_id' => $row['user_id'],
                'payment_number' => 'PAYINST-'.$run.'-'.$index, 'payment_date' => '2026-10-02', 'amount' => 1000];
            $paymentId = $call('POST', '/api/v1/payments', $payment)['payment']['id'];
            $expense = ['school_level_id' => $idLevel, 'expense_category_id' => $fixture['category'], 'expense_date' => '2026-10-02', 'amount' => 500];
            $expenseId = $call('POST', '/api/v1/expenses', $expense)['expense']['id'];
            $student = ['school_level_id' => $idLevel, 'academic_year_id' => $row['academic_year_id'], 'grade_level_id' => $row['grade_level_id'],
                'division_id' => $row['division_id'], 'first_name' => 'Alta ficticia', 'last_name' => $run.'-'.$index, 'status' => 'active'];
            $studentId = $call('POST', '/api/v1/students', $student, null, 201)['student']['id'];
            foreach (['items' => [$itemId, $item], 'invoices' => [$invoiceId, $invoice], 'estimates' => [$estimateId, $estimate],
                'payments' => [$paymentId, $payment], 'expenses' => [$expenseId, $expense], 'students' => [$studentId, $student]] as $resource => [$id, $payload]) {
                foreach ([null, $idLevel] as $context) {
                    $call('GET', '/api/v1/'.$resource.'/'.$id, [], $context);
                    $call('PUT', '/api/v1/'.$resource.'/'.$id, $payload, $context);
                    if ((int) DB::table($resource)->where('id', $id)->value('school_level_id') !== $idLevel) throw new \RuntimeException('Se perdió el nivel de '.$resource);
                }
                // Nivel omitido en edición: también conserva el original.
                $omitted = $payload; unset($omitted['school_level_id']);
                $call('PUT', '/api/v1/'.$resource.'/'.$id, $omitted);
                // Altas globales sin nivel son validación 422, nunca un registro huérfano.
                $call('POST', '/api/v1/'.$resource, $omitted, null, 422);
                $different = $payload; $different['school_level_id'] = $fixture['levels'][($index + 1) % 3]['school_level_id'];
                $call('PUT', '/api/v1/'.$resource.'/'.$id, $different, null, $resource === 'students' ? 422 : 403);
                if ((int) DB::table($resource)->where('id', $id)->value('school_level_id') !== $idLevel) throw new \RuntimeException('Una edición cambió el nivel de '.$resource);
            }
            $records[] = compact('invoiceId', 'paymentId', 'expenseId', 'itemId');
        }
        // Fila 5: baja lógica y papelera con los documentos recién creados del primer nivel.
        [$first, $second] = [$records[0], $records[1]];
        $level = (int) $fixture['levels'][0]['school_level_id'];
        $due = fn () => (int) DB::table('invoices')->where('id', $first['invoiceId'])->value('due_amount');
        $trashed = fn ($table, $id) => DB::table($table)->where('id', $id)->whereNotNull('deleted_at')->exists();
        $call('POST', '/api/v1/invoices/delete', ['ids' => [$first['invoiceId']]], null, 422);
        $call('POST', '/api/v1/items/delete', ['ids' => [$first['itemId']]], null, 422);
        $call('POST', '/api/v1/expenses/delete', ['ids' => [$first['expenseId'], $second['expenseId']]], $level, 403);
        if ($trashed('expenses', $first['expenseId'])) throw new \RuntimeException('Un lote rechazado dio de baja un gasto');
        $call('POST', '/api/v1/payments/delete', ['ids' => [$first['paymentId']]]);
        if ($due() !== 10000 || ! $trashed('payments', $first['paymentId'])) throw new \RuntimeException('La baja del cobro no devolvió el saldo');
        $call('POST', '/api/v1/invoices/delete', ['ids' => [$first['invoiceId']]]);
        $call('GET', '/api/v1/invoices/'.$first['invoiceId'], [], null, 404);
        if (! in_array($first['invoiceId'], array_column($call('GET', '/api/v1/trash/invoices?limit=100')['records']['data'] ?? [], 'id'), true)) {
            throw new \RuntimeException('La factura dada de baja no figura en la papelera');
        }
        $call('POST', '/api/v1/trash/payments/'.$first['paymentId'].'/restore', [], null, 422);
        $call('POST', '/api/v1/trash/invoices/'.$first['invoiceId'].'/restore');
        $call('POST', '/api/v1/trash/payments/'.$first['paymentId'].'/restore');
        if ($due() !== 9000 || $trashed('invoices', $first['invoiceId']) || $trashed('payments', $first['paymentId'])) {
            throw new \RuntimeException('La restauración no repuso documentos y saldo');
        }
        $call('POST', '/api/v1/expenses/delete', ['ids' => [$first['expenseId']]], $level);
        $call('POST', '/api/v1/trash/expenses/'.$first['expenseId'].'/restore');
        if ($trashed('expenses', $first['expenseId'])) throw new \RuntimeException('El gasto no se restauró');
        // Fila 7: pase entre niveles con historial y corrección de vuelta, sobre el alumno ficticio del primer nivel.
        $studentId = (int) DB::table('students')->where('company_id', $company)->where('school_level_id', $level)
            ->where('last_name', $run.'-0')->value('id');
        $target = $fixture['levels'][1];
        $origin = $fixture['levels'][0];
        $move = fn ($row, $mode) => ['mode' => $mode, 'school_level_id' => $row['school_level_id'], 'academic_year_id' => $row['academic_year_id'],
            'grade_level_id' => $row['grade_level_id'], 'division_id' => $row['division_id'], 'reason' => 'Ensayo ficticio de fila 7'];
        $call('PUT', '/api/v1/students/'.$studentId.'/relocate', $move($target, 'transfer'));
        $history = $call('GET', '/api/v1/students/'.$studentId.'/enrollments', [], (int) $target['school_level_id'])['data'] ?? [];
        if (array_column($history, 'status') !== ['transferred_out', 'active']) throw new \RuntimeException('El pase no dejó historial');
        $call('PUT', '/api/v1/students/'.$studentId.'/relocate', $move($origin, 'correction'));
        if (DB::table('enrollments')->where('student_id', $studentId)->where('status', 'active')->count() !== 1
            || (int) DB::table('students')->where('id', $studentId)->value('school_level_id') !== $level) throw new \RuntimeException('La corrección no dejó una sola matrícula vigente');

        // Fila E1: materias por curso, alta unificada de un docente ficticio y revocación inmediata.
        $subjectId = DB::table('subjects')->where('company_id', $company)->where('school_level_id', $level)->value('id')
            ?? DB::table('subjects')->insertGetId(['company_id' => $company, 'school_level_id' => $level,
                'name' => 'Materia ficticia institución', 'created_at' => now(), 'updated_at' => now()]);
        $divisionId = (int) $fixture['levels'][0]['division_id'];
        $kept = DB::table('course_sections')->where('division_id', $divisionId)->where('status', 'active')->pluck('subject_id')->all();
        $call('PUT', '/api/v1/divisions/'.$divisionId.'/course-sections', ['subject_ids' => array_values(array_unique(array_merge($kept, [$subjectId])))], $level);
        $sectionId = (int) DB::table('course_sections')->where('division_id', $divisionId)->where('subject_id', $subjectId)->value('id');
        $roleId = DB::table('roles')->where('company_id', $company)->where('name', 'teacher')->value('id');
        $file = $call('POST', '/api/v1/staff/onboard', ['first_name' => 'Docente ficticio', 'last_name' => $run,
            'teaching' => [['course_section_id' => $sectionId, 'weekly_hours' => 2]],
            'access' => ['enabled' => true, 'email' => 'docente.'.strtolower($run).'@example.invalid', 'password' => bin2hex(random_bytes(8))]], $level, 201);
        $teacherId = (int) ($file['access']['user_id'] ?? 0);
        $grant = fn () => DB::table('role_user')->where('user_id', $teacherId)->where('role_id', $roleId)
            ->where('course_section_id', $sectionId)->whereNull('revoked_at')->count();
        if ($teacherId === 0 || $grant() !== 1 || empty($file['access']['must_change_password'])) throw new \RuntimeException('El alta docente no otorgó acceso y rol de sección');
        $call('POST', '/api/v1/staff/'.$file['member']['id'].'/teaching/'.$file['teaching'][0]['id'].'/revoke', [], $level);
        if ($grant() !== 0) throw new \RuntimeException('Revocar la asignación docente no retiró el rol');
        $call('POST', '/api/v1/staff/'.$file['member']['id'].'/access/disable', [], $level);

        // Reportes PDF reales: consolidado y los tres filtros, sin reemplazar controladores.
        foreach (['sales/customers', 'sales/items', 'expenses', 'tax-summary', 'profit-loss'] as $report) {
            foreach ([null, ...array_column($fixture['levels'], 'school_level_id')] as $level) {
                $path = '/reports/'.$report.'/'.$hash.'?from_date=2026-01-01&to_date=2026-12-31&school_level_id='.($level ?? '');
                $response = $request('GET', $path, [], null);
                $checks[] = ['method' => 'GET', 'path' => $path, 'expected' => 200, 'actual' => $response['status']];
                if ($response['status'] !== 200 || strpos($response['body'] ?? '', '%PDF-') !== 0) throw new \RuntimeException('No se generó el PDF '.$report.' ('.$response['status'].').');
            }
        }
        return ['passed' => true, 'checks' => $checks, 'count' => count($checks), 'levels' => 3,
            'preserved_levels' => true, 'required_creation_level' => true, 'real_report_pdfs' => true,
            'soft_delete_round_trip' => true, 'staff_onboarding' => true, 'enrollment_history' => true];
    }
}
