<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRegistrationNumberToSchoolLevels extends Migration
{
    public function up()
    {
        Schema::table('school_levels', function (Blueprint $table) {
            $table->string('registration_number', 100)->nullable()->after('jurisdiction_code');
        });

        DB::table('school_levels')
            ->where('code', 'tertiary')
            ->whereNull('registration_number')
            ->update(['registration_number' => '1831']);
    }

    public function down()
    {
        Schema::table('school_levels', function (Blueprint $table) {
            $table->dropColumn('registration_number');
        });
    }
}
