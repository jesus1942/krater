<?php

use Crater\Http\Controllers\V1\General\NextNumberController;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    TenantContext::clear();

    foreach (['invoices', 'estimates', 'payments', 'company_settings'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('company_settings', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('option');
        $table->string('value')->nullable();
    });

    foreach (['invoices', 'estimates', 'payments'] as $tableName) {
        Schema::create($tableName, function (Blueprint $table) use ($tableName) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $column = $tableName === 'invoices'
                ? 'invoice_number'
                : ($tableName === 'estimates' ? 'estimate_number' : 'payment_number');
            $table->string($column);
        });
    }

    DB::table('company_settings')->insert([
        ['company_id' => 1, 'option' => 'invoice_number_length', 'value' => '6'],
        ['company_id' => 1, 'option' => 'estimate_number_length', 'value' => '6'],
        ['company_id' => 1, 'option' => 'payment_number_length', 'value' => '6'],
    ]);

    app()->instance('request', Request::create('/test', 'GET', [], [], [], [
        'HTTP_COMPANY' => '1',
        'HTTP_SCHOOL_LEVEL' => '10',
    ]));

    TenantContext::set(1, 10);
});

afterEach(function () {
    TenantContext::clear();
    foreach (['invoices', 'estimates', 'payments', 'company_settings'] as $table) {
        Schema::dropIfExists($table);
    }
});

test('next number controller validates tenant before calculating sequence', function () {
    $middleware = collect(app(NextNumberController::class)->getMiddleware())
        ->pluck('middleware')
        ->all();

    expect($middleware)->toContain('tenant');
});

test('invoice sequence is company wide across levels and never crosses companies', function () {
    DB::table('invoices')->insert([
        ['company_id' => 1, 'school_level_id' => 10, 'invoice_number' => 'INV-000005'],
        ['company_id' => 1, 'school_level_id' => 30, 'invoice_number' => 'INV-000009'],
        ['company_id' => 2, 'school_level_id' => 10, 'invoice_number' => 'INV-000099'],
    ]);

    expect(Invoice::getNextInvoiceNumber('INV'))->toBe('000010');
});

test('estimate sequence is company wide across levels and never crosses companies', function () {
    DB::table('estimates')->insert([
        ['company_id' => 1, 'school_level_id' => 10, 'estimate_number' => 'EST-000003'],
        ['company_id' => 1, 'school_level_id' => 30, 'estimate_number' => 'EST-000007'],
        ['company_id' => 2, 'school_level_id' => 10, 'estimate_number' => 'EST-000088'],
    ]);

    expect(Estimate::getNextEstimateNumber('EST'))->toBe('000008');
});

test('payment sequence is company wide across levels and never crosses companies', function () {
    DB::table('payments')->insert([
        ['company_id' => 1, 'school_level_id' => 10, 'payment_number' => 'PAY-000002'],
        ['company_id' => 1, 'school_level_id' => 30, 'payment_number' => 'PAY-000006'],
        ['company_id' => 2, 'school_level_id' => 10, 'payment_number' => 'PAY-000077'],
    ]);

    expect(Payment::getNextPaymentNumber('PAY'))->toBe('000007');
});
