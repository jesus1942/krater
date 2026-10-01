<?php

use Crater\Http\Controllers\V1\Student\StudentRelocationController;
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

it('audita los modelos economicos alumnos y recursos humanos definidos por etapa 1', function () {
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
        expect(class_uses_recursive($modelClass))->toContain(Auditable::class);
    }
});

it('redacta datos personales innecesarios pero conserva el motivo operativo', function () {
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
        expect($clean[$field])->toBe('[omitido]');
    }

    expect($clean['school_level_id'])->toBe(3)
        ->and($clean['reason'])->toBe('Correccion de carga administrativa');
});

it('registra reubicacion semantica con motivo actor origen y destino', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/V1/Student/StudentRelocationController.php');
    $vue = file_get_contents($root.'/resources/assets/js/views/students/Index.vue');

    expect($controller)
        ->toContain("'reason' => ['required', 'string', 'max:500']")
        ->and($controller)->toContain("'student_relocated'")
        ->and($controller)->toContain("Auth::user()")
        ->and($controller)->toContain("'school_level_id' => $student->school_level_id")
        ->and($controller)->toContain("'academic_year_id' => $existing ? (int) $existing->academic_year_id : null")
        ->and($controller)->toContain("'grade_level_id' => (int) $gradeLevel->id")
        ->and($controller)->toContain("'division_id' => (int) $division->id")
        ->and($controller)->toContain("AuditLog::SEVERITY_HIGH");

    expect($vue)
        ->toContain('Motivo *')
        ->and($vue)->toContain('v-model="relocationForm.reason"')
        ->and($vue)->toContain("reason: ''");
});
