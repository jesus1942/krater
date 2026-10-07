<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentsTable extends Migration
{
    public function up()
    {
        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('guardian_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('dni')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('level')->nullable();
            $table->string('grade')->nullable();
            $table->string('division')->nullable();
            $table->unsignedSmallInteger('school_year');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('guardian_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['company_id', 'dni']);
            $table->index(['company_id', 'school_year', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('students');
    }
}
