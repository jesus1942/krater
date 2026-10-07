<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSchoolLevelsAndScopes extends Migration
{
    public function up()
    {
        Schema::create('school_levels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->string('code');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('cue')->nullable();
            $table->string('jurisdiction_code')->nullable();
            $table->string('resolution_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('billing_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('director_name')->nullable();
            $table->string('secretary_name')->nullable();
            $table->string('accounting_contact')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['company_id', 'code']);
        });

        Schema::create('school_level_user', function (Blueprint $table) {
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('user_id');
            $table->string('role')->default('member');
            $table->timestamps();
            $table->primary(['school_level_id', 'user_id']);
            $table->foreign('school_level_id')->references('id')->on('school_levels')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        foreach (DB::table('companies')->get() as $company) {
            $now = now();
            $ids = [];
            foreach ([
                ['code' => 'primary', 'name' => 'Nivel Primario'],
                ['code' => 'secondary', 'name' => 'Nivel Secundario'],
                ['code' => 'tertiary', 'name' => 'Nivel Terciario'],
            ] as $level) {
                $ids[] = DB::table('school_levels')->insertGetId([
                    'company_id' => $company->id,
                    'code' => $level['code'],
                    'name' => $level['name'],
                    'legal_name' => $company->name,
                    'province' => 'Chubut',
                    'enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $admins = DB::table('users')->where('company_id', $company->id)
                ->whereIn('role', ['super admin', 'admin'])->pluck('id');
            foreach ($ids as $levelId) {
                foreach ($admins as $userId) {
                    DB::table('school_level_user')->insert([
                        'school_level_id' => $levelId,
                        'user_id' => $userId,
                        'role' => 'administrator',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        foreach (['students', 'invoices', 'estimates', 'payments', 'expenses', 'items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('school_level_id')->nullable()->index();
                $table->foreign('school_level_id')->references('id')->on('school_levels')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        foreach (['students', 'invoices', 'estimates', 'payments', 'expenses', 'items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['school_level_id']);
                $table->dropColumn('school_level_id');
            });
        }
        Schema::dropIfExists('school_level_user');
        Schema::dropIfExists('school_levels');
    }
}
