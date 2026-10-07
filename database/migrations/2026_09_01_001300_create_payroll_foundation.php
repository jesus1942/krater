<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayrollFoundation extends Migration
{
    public function up()
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('school_level_id')->references('id')->on('school_levels')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['company_id', 'school_level_id', 'year', 'month'], 'payroll_period_unique');
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('payroll_period_id');
            $table->unsignedInteger('staff_member_id');
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('deductions_amount')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('school_level_id')->references('id')->on('school_levels')->onDelete('restrict');
            $table->foreign('payroll_period_id')->references('id')->on('payroll_periods')->onDelete('restrict');
            $table->foreign('staff_member_id')->references('id')->on('staff_members')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['payroll_period_id', 'staff_member_id'], 'payroll_period_staff_unique');
            $table->index(['company_id', 'school_level_id', 'status'], 'payroll_slip_scope_status_idx');
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('payroll_slip_id');
            $table->unsignedBigInteger('amount');
            $table->date('paid_at');
            $table->string('payment_method', 40)->default('transfer');
            $table->string('reference', 160)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedInteger('reversed_by')->nullable();
            $table->string('reversal_reason', 255)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('school_level_id')->references('id')->on('school_levels')->onDelete('restrict');
            $table->foreign('payroll_slip_id')->references('id')->on('payroll_slips')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reversed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['payroll_slip_id', 'reversed_at'], 'payroll_payment_active_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_periods');
    }
}
