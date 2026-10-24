<?php

use Crater\Http\Controllers\V1\Item\ItemsController;
use Crater\Models\Item;
use Crater\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    TenantContext::clear();

    Schema::dropIfExists('items');
    Schema::dropIfExists('school_levels');

    Schema::create('school_levels', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('code');
        $table->string('name');
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    Schema::create('items', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('school_level_id')->nullable();
        $table->string('name');
        $table->text('description')->nullable();
        $table->integer('price')->default(0);
        $table->unsignedInteger('unit_id')->nullable();
        $table->unsignedInteger('creator_id')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    TenantContext::clear();
    Schema::dropIfExists('items');
    Schema::dropIfExists('school_levels');
});

test('items controller initializes tenant context middleware', function () {
    $middleware = collect(app(ItemsController::class)->getMiddleware())
        ->pluck('middleware')
        ->all();

    expect($middleware)->toContain('tenant');
});

test('item is stamped with active level and hidden from another level', function () {
    TenantContext::set(1, 10);

    $item = Item::create([
        'company_id' => 1,
        'name' => 'Concepto exclusivo primaria',
        'price' => 470000,
    ]);

    expect($item->school_level_id)->toBe(10);
    expect(Item::whereKey($item->id)->exists())->toBeTrue();

    TenantContext::clear();
    TenantContext::set(1, 30);

    expect(Item::whereKey($item->id)->exists())->toBeFalse();

    TenantContext::clear();
    expect(Item::withoutGlobalScopes()->whereKey($item->id)->exists())->toBeTrue();
});

test('legacy cuota nivel backfill assigns only the primary level', function () {
    $primaryId = DB::table('school_levels')->insertGetId([
        'company_id' => 1,
        'code' => 'primary',
        'name' => 'Nivel Primario',
        'enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('school_levels')->insert([
        'company_id' => 1,
        'code' => 'tertiary',
        'name' => 'Nivel Terciario',
        'enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('items')->insert([
        [
            'company_id' => 1,
            'school_level_id' => null,
            'name' => 'Cuota nivel',
            'price' => 470000,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => 1,
            'school_level_id' => null,
            'name' => 'Artículo legacy sin procedencia',
            'price' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    require_once base_path('database/migrations/2026_09_01_000700_add_school_level_to_items.php');
    (new AddSchoolLevelToItems())->up();

    expect((int) DB::table('items')->where('name', 'Cuota nivel')->value('school_level_id'))
        ->toBe((int) $primaryId);

    expect(DB::table('items')->where('name', 'Artículo legacy sin procedencia')->value('school_level_id'))
        ->toBeNull();
});
