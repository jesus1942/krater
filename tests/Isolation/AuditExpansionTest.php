<?php

namespace Tests\Isolation;

use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\PayrollPayment;
use Crater\Models\PayrollSlip;
use Crater\Models\StaffMember;
use Crater\Models\Student;
use Crater\Services\Audit\Auditor;
use Crater\Traits\Auditable;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AuditExpansionTest extends TestCase
{
    public function testCriticalModelsUseAuditableTrait(): void
    {
        foreach ([
            Invoice::class,
            Estimate::class,
            Payment::class,
            Expense::class,
            Student::class,
            StaffMember::class,
            PayrollSlip::class,
            PayrollPayment::class,
        ] as $modelClass) {
            $this->assertContains(
                Auditable::class,
                class_uses($modelClass),
                $modelClass.' debe usar Auditable'
            );
        }
    }

    public function testPersonalDataIsRedactedButOperationalReasonIsKept(): void
    {
        $auditor = new Auditor();
        $method = new ReflectionMethod(Auditor::class, 'limpiar');
        $method->setAccessible(true);

        $clean = $method->invoke($auditor, [
            'dni' => '12345678',
            'birth_date' => '2010-01-02',
            'document_number' => '30111222',
            'document_number_normalized' => '30111222',
            'first_name' => 'Nombre',
            'last_name' => 'Apellido',
            'email' => 'persona@example.com',
            'phone' => '2800000000',
            'school_level_id' => 3,
            'reason' => 'Correccion de carga administrativa',
        ]);

        foreach ([
            'dni',
            'birth_date',
            'document_number',
            'document_number_normalized',
            'first_name',
            'last_name',
            'email',
            'phone',
        ] as $field) {
            $this->assertSame('[omitido]', $clean[$field]);
        }

        $this->assertSame(3, $clean['school_level_id']);
        $this->assertSame('Correccion de carga administrativa', $clean['reason']);
    }

    public function testRelocationHasSemanticAuditWithReasonActorOriginAndDestination(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root.'/app/Http/Controllers/V1/Student/StudentRelocationController.php');
        $vue = file_get_contents($root.'/resources/assets/js/views/students/Index.vue');

        $this->assertStringContainsString("'reason' => ['required', 'string', 'max:500']", $controller);
        $this->assertStringContainsString("'student_relocated'", $controller);
        $this->assertStringContainsString('Auth::user()', $controller);
        $this->assertStringContainsString('\'school_level_id\' => $student->school_level_id', $controller);
        $this->assertStringContainsString('\'academic_year_id\' => $existing ? (int) $existing->academic_year_id : null', $controller);
        $this->assertStringContainsString('\'grade_level_id\' => (int) $gradeLevel->id', $controller);
        $this->assertStringContainsString('\'division_id\' => (int) $division->id', $controller);
        $this->assertStringContainsString('AuditLog::SEVERITY_HIGH', $controller);

        $this->assertStringContainsString('Motivo *', $vue);
        $this->assertStringContainsString('v-model="relocationForm.reason"', $vue);
        $this->assertStringContainsString("reason: ''", $vue);
    }
}
