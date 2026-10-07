<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    foreach (['divisions', 'grade_levels', 'study_plans', 'academic_years', 'school_levels'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('school_levels', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('code');
        $table->string('name');
        $table->boolean('enabled')->default(true);
    });
    Schema::create('academic_years', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('school_level_id');
        $table->smallInteger('year');
        $table->string('name');
        $table->date('starts_on');
        $table->date('ends_on');
        $table->string('status');
        $table->timestamps();
    });
    Schema::create('study_plans', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('school_level_id');
        $table->smallInteger('duration_years');
        $table->boolean('enabled')->default(true);
    });
    Schema::create('grade_levels', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('school_level_id');
        $table->string('name');
        $table->smallInteger('position');
        $table->unsignedInteger('promotes_to_id')->nullable();
        $table->string('pedagogical_unit')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });
    Schema::create('divisions', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('school_level_id');
        $table->unsignedInteger('academic_year_id');
        $table->unsignedInteger('grade_level_id');
        $table->string('name');
        $table->string('shift')->nullable();
        $table->smallInteger('capacity')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    DB::table('school_levels')->insert([
        ['id' => 10, 'company_id' => 1, 'code' => 'primary', 'name' => 'Nivel Primario', 'enabled' => true],
        ['id' => 20, 'company_id' => 1, 'code' => 'secondary', 'name' => 'Nivel Secundario', 'enabled' => true],
        ['id' => 30, 'company_id' => 1, 'code' => 'tertiary', 'name' => 'Nivel Terciario', 'enabled' => true],
    ]);
    DB::table('study_plans')->insert([
        'company_id' => 1,
        'school_level_id' => 30,
        'duration_years' => 3,
        'enabled' => true,
    ]);
});

afterEach(function () {
    foreach (['divisions', 'grade_levels', 'study_plans', 'academic_years', 'school_levels'] as $table) {
        Schema::dropIfExists($table);
    }
});

it('creates usable 2026 cycles grades and divisions and is idempotent', function () {
    require_once base_path('database/migrations/2026_09_01_001000_seed_usable_academic_structure.php');
    $migration = new SeedUsableAcademicStructure();
    $migration->up();

    expect(DB::table('academic_years')->where('company_id', 1)->where('year', 2026)->count())->toBe(3);
    expect(DB::table('grade_levels')->where('school_level_id', 10)->count())->toBe(6);
    expect(DB::table('grade_levels')->where('school_level_id', 20)->count())->toBe(6);
    expect(DB::table('grade_levels')->where('school_level_id', 30)->count())->toBe(3);
    expect(DB::table('grade_levels')->where('school_level_id', 10)->where('name', '5.º grado')->exists())->toBeTrue();
    expect(DB::table('divisions')->where('school_level_id', 10)->where('name', 'A')->count())->toBe(6);

    $before = [
        DB::table('academic_years')->count(),
        DB::table('grade_levels')->count(),
        DB::table('divisions')->count(),
    ];
    $migration->up();
    $after = [
        DB::table('academic_years')->count(),
        DB::table('grade_levels')->count(),
        DB::table('divisions')->count(),
    ];

    expect($after)->toBe($before);
});
