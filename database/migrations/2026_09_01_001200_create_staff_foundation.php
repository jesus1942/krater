<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaffFoundation extends Migration
{
    public function up()
    {
        Schema::create('staff_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('document_type', 30)->nullable();
            $table->string('document_number', 50)->nullable();
            $table->string('document_number_normalized', 50)->nullable();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('email', 150)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('staff_category', 40);
            $table->string('employment_status', 30)->default('active');
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['company_id', 'document_number_normalized'], 'staff_company_document_unique');
            $table->index(['company_id', 'staff_category', 'employment_status'], 'staff_company_category_status_idx');
        });

        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('staff_member_id');
            $table->unsignedBigInteger('school_level_id')->nullable();
            $table->string('position_code', 60)->nullable();
            $table->string('position_title', 150);
            $table->string('function_category', 40);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->decimal('weekly_hours', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('staff_member_id')->references('id')->on('staff_members')->onDelete('cascade');
            $table->foreign('school_level_id')->references('id')->on('school_levels')->onDelete('restrict');
            $table->index(['company_id', 'school_level_id', 'active'], 'staff_assignment_level_active_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff_assignments');
        Schema::dropIfExists('staff_members');
    }
}
