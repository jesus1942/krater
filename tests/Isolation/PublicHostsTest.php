<?php

namespace Tests\Isolation;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** Landing en el dominio principal y www; la Suite en app.<dominio>. */
class PublicHostsTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_without_landing_hosts_nothing_changes(): void
    {
        config(['ena-hosts.landing_hosts' => [], 'app.url' => 'https://krater.example.invalid']);
        $this->get('http://krater.example.invalid/')->assertOk()->assertSee('Nueva Austral');
        // /login sigue en el mismo host (sin instalar, el propio login redirige al asistente).
        $this->assertStringNotContainsString('app.', (string) $this->get('http://krater.example.invalid/login')->headers->get('Location'));
    }

    public function test_landing_hosts_serve_only_the_landing_and_send_the_rest_to_the_suite(): void
    {
        config(['ena-hosts.landing_hosts' => ['escuela.example.invalid', 'www.escuela.example.invalid'],
            'app.url' => 'https://app.escuela.example.invalid']);

        $landing = $this->get('https://escuela.example.invalid/')->assertOk();
        $landing->assertSee('https://app.escuela.example.invalid/login', false);
        $this->get('https://www.escuela.example.invalid/')->assertOk();
        $this->get('https://escuela.example.invalid/login')->assertStatus(301)
            ->assertRedirect('https://app.escuela.example.invalid/login');
        $this->get('https://www.escuela.example.invalid/admin/students?nivel=2')->assertStatus(301)
            ->assertRedirect('https://app.escuela.example.invalid/admin/students?nivel=2');

        // En la Suite la raiz lleva al ingreso; el resto sigue igual.
        $this->get('https://app.escuela.example.invalid/')->assertRedirect('/admin');
        $this->assertNotSame(301, $this->get('https://app.escuela.example.invalid/login')->getStatusCode());
    }
}
