<?php

use Crater\Models\Item;
use Crater\Models\Tax;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::findOrFail(1);

    Sanctum::actingAs($this->user, ['*']);

    $ensureLevel = function (string $code, string $name) {
        $id = DB::table('school_levels')
            ->where('company_id', $this->user->company_id)
            ->where('code', $code)
            ->value('id');

        if (! $id) {
            $id = DB::table('school_levels')->insertGetId([
                'company_id' => $this->user->company_id,
                'code' => $code,
                'name' => $name,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('school_level_user')->updateOrInsert([
            'school_level_id' => $id,
            'user_id' => $this->user->id,
        ], [
            'role' => 'administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    };

    $this->primaryId = $ensureLevel('primary', 'Nivel Primario');
    $this->tertiaryId = $ensureLevel('tertiary', 'Nivel Terciario');
});

test('creating an item stores the active school level', function () {
    $payload = Item::factory()->raw([
        'name' => 'Cuota primaria regresion',
        'taxes' => [Tax::factory()->raw()],
    ]);

    $response = $this->withHeaders([
        'company' => $this->user->company_id,
        'school-level' => $this->primaryId,
    ])->postJson('/api/v1/items', $payload);

    $response->assertOk();

    $this->assertDatabaseHas('items', [
        'name' => 'Cuota primaria regresion',
        'company_id' => $this->user->company_id,
        'school_level_id' => $this->primaryId,
    ]);
});

test('an item from primary is not listed in tertiary', function () {
    Item::factory()->create([
        'name' => 'Concepto exclusivo primaria',
        'company_id' => $this->user->company_id,
        'school_level_id' => $this->primaryId,
    ]);

    $primaryResponse = $this->withHeaders([
        'company' => $this->user->company_id,
        'school-level' => $this->primaryId,
    ])->getJson('/api/v1/items?page=1');

    $primaryResponse->assertOk();
    $primaryResponse->assertJsonFragment(['name' => 'Concepto exclusivo primaria']);

    $tertiaryResponse = $this->withHeaders([
        'company' => $this->user->company_id,
        'school-level' => $this->tertiaryId,
    ])->getJson('/api/v1/items?page=1');

    $tertiaryResponse->assertOk();
    $tertiaryResponse->assertJsonMissing(['name' => 'Concepto exclusivo primaria']);
});
