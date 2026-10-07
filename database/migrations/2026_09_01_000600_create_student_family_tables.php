<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentFamilyTables extends Migration
{
    public function up()
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name', 190);
            $table->string('dni', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['company_id', 'dni'], 'family_members_company_dni_unique');
            $table->unique(['company_id', 'user_id'], 'family_members_company_user_unique');
            $table->index(['company_id', 'name']);
        });

        Schema::create('student_family_members', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('family_member_id');
            $table->string('relationship', 40)->nullable();
            $table->boolean('is_responsible')->default(false);
            $table->boolean('is_financial_responsible')->default(false);
            $table->boolean('is_primary_contact')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('family_member_id')->references('id')->on('family_members')->cascadeOnDelete();
            $table->unique(['student_id', 'family_member_id'], 'student_family_member_unique');
            $table->index(['company_id', 'is_responsible'], 'student_family_responsible_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_family_members');
        Schema::dropIfExists('family_members');
    }
}
