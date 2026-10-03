<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LinkSchoolBillingToStudentsAndFamily extends Migration
{
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('student_id')->nullable()->after('school_level_id')->index();
            $table->unsignedInteger('enrollment_id')->nullable()->after('student_id')->index();
            $table->unsignedInteger('family_member_id')->nullable()->after('enrollment_id')->index();

            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->nullOnDelete();
            $table->foreign('family_member_id')->references('id')->on('family_members')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('student_id')->nullable()->after('school_level_id')->index();
            $table->unsignedInteger('family_member_id')->nullable()->after('student_id')->index();

            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            $table->foreign('family_member_id')->references('id')->on('family_members')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['family_member_id']);
            $table->dropForeign(['student_id']);
            $table->dropColumn(['family_member_id', 'student_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['family_member_id']);
            $table->dropForeign(['enrollment_id']);
            $table->dropForeign(['student_id']);
            $table->dropColumn(['family_member_id', 'enrollment_id', 'student_id']);
        });
    }
}
