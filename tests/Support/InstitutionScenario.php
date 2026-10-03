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
            $records[] = $invoiceId;
        }
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
            'preserved_levels' => true, 'required_creation_level' => true, 'real_report_pdfs' => true];
    }
}
