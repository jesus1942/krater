<?php

namespace Tests\Isolation;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/** Verifica el orden real de los catalogos sin cuentas ni datos de la escuela. */
class FreshInstallationCatalogTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        foreach ([
            '2014_10_11_125754_create_currencies_table.php',
            '2014_10_12_000010_seed_base_data.php',
            '2014_10_12_000011_seed_countries.php',
            '2017_05_06_173745_create_countries_table.php',
        ] as $file) {
            require_once database_path('migrations/'.$file);
        }

        (new \CreateCurrenciesTable())->up();
    }

    /** La carga de 2014 no debe consultar tablas que se crean en 2017/2019. */
    public function test_early_seeds_work_before_institutional_tables_exist(): void
    {
        (new \SeedBaseData())->up();
        (new \SeedCountries())->up();

        $this->assertSame(14, DB::table('currencies')->count());
        $this->assertFalse(Schema::hasTable('payment_methods'));
        $this->assertFalse(Schema::hasTable('countries'));

        (new \CreateCountriesTable())->up();
        $this->assertGreaterThan(200, DB::table('countries')->count());
        $this->assertTrue(DB::table('countries')->where('code', 'AR')->exists());
    }

    /** Repetir los seeds conserva los catalogos y los valores personalizados. */
    public function test_repeated_seeds_do_not_overwrite_existing_catalogs(): void
    {
        (new \SeedBaseData())->up();
        (new \CreateCountriesTable())->up();
        DB::table('currencies')->where('code', 'ARS')->update(['name' => 'Moneda personalizada']);
        DB::table('countries')->where('code', 'AR')->update(['name' => 'Pais personalizado']);
        $countries = DB::table('countries')->count();

        (new \SeedBaseData())->up();
        (new \SeedCountries())->up();

        $this->assertSame(14, DB::table('currencies')->count());
        $this->assertSame($countries, DB::table('countries')->count());
        $this->assertSame('Moneda personalizada', DB::table('currencies')->where('code', 'ARS')->value('name'));
        $this->assertSame('Pais personalizado', DB::table('countries')->where('code', 'AR')->value('name'));
    }

    /** Con tablas vacias pero sin empresa no deben aparecer FK huerfanas. */
    public function test_seeds_do_not_invent_company_one(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
        });
        foreach (['payment_methods', 'units'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->unsignedInteger('company_id');
                $table->foreign('company_id')->references('id')->on('companies');
                $table->timestamps();
            });
        }

        (new \SeedBaseData())->up();

        $this->assertSame(0, DB::table('companies')->count());
        $this->assertSame(0, DB::table('payment_methods')->count());
        $this->assertSame(0, DB::table('units')->count());
    }
}
