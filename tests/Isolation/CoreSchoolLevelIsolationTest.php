<?php

use Crater\Http\Controllers\V1\Estimate\EstimatesController;
use Crater\Http\Controllers\V1\Expense\ExpensesController;
use Crater\Http\Controllers\V1\Invoice\InvoicesController;
use Crater\Http\Controllers\V1\Payment\PaymentsController;
use Crater\Http\Controllers\V1\Student\StudentsController;
use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\Student;
use Crater\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

$tables = ['students', 'estimates', 'invoices', 'payments', 'expenses'];

beforeEach(function () use ($tables) {
    TenantContext::clear();

    foreach ($tables as $tableName) {
        Schema::dropIfExists($tableName);
        Schema::create($tableName, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
        });
    }
});

afterEach(function () use ($tables) {
    TenantContext::clear();
    foreach ($tables as $tableName) {
        Schema::dropIfExists($tableName);
    }
});

test('core controllers initialize tenant context', function () {
    $controllers = [
        StudentsController::class,
        EstimatesController::class,
        InvoicesController::class,
        PaymentsController::class,
        ExpensesController::class,
    ];

    foreach ($controllers as $controller) {
        $middleware = collect(app($controller)->getMiddleware())
            ->pluck('middleware')
            ->all();

        expect($middleware)->toContain('tenant', $controller.' must initialize tenant context');
    }
});

test('core models cannot leak records between school levels', function () {
    $cases = [
        [Student::class, 'students'],
        [Estimate::class, 'estimates'],
        [Invoice::class, 'invoices'],
        [Payment::class, 'payments'],
        [Expense::class, 'expenses'],
    ];

    foreach ($cases as [$model, $table]) {
        $id = DB::table($table)->insertGetId([
            'company_id' => 1,
            'school_level_id' => 10,
        ]);

        TenantContext::set(1, 10);
        expect($model::whereKey($id)->exists())->toBeTrue($model.' should be visible in its own level');

        TenantContext::clear();
        TenantContext::set(1, 30);
        expect($model::whereKey($id)->exists())->toBeFalse($model.' leaked into another school level');

        TenantContext::clear();
        TenantContext::set(2, 10);
        expect($model::whereKey($id)->exists())->toBeFalse($model.' leaked into another company');

        TenantContext::clear();
    }
});
