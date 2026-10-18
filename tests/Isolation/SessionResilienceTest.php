<?php

namespace Tests\Isolation;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/** Arreglos de sesion: aviso al perder la sesion, 419 y sesiones que sobreviven a un deploy. */
class SessionResilienceTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_api_without_session_is_401_and_admin_with_stale_cookie_goes_to_login_with_notice(): void
    {
        $this->getJson('/api/v1/students')->assertStatus(401);
        $this->getJson('/api/v1/bootstrap')->assertStatus(401);

        $middleware = new \Crater\Http\Middleware\RedirectIfUnauthorized();
        $next = fn () => response('ok');
        $stale = \Illuminate\Http\Request::create('/admin/students', 'GET', [], [config('session.cookie') => 'vencida']);
        $this->assertSame(url('/login?aviso=sesion'), $middleware->handle($stale, $next)->getTargetUrl());
        $fresh = \Illuminate\Http\Request::create('/admin/students', 'GET');
        $this->assertSame(url('/login'), $middleware->handle($fresh, $next)->getTargetUrl());

        $active = new \Crater\Http\Middleware\ActiveAccount();
        $this->assertSame(401, $active->handle(\Illuminate\Http\Request::create('/api/v1/me', 'GET'), $next)->getStatusCode());
    }

    public function test_sessions_default_to_database_and_runtime_report_flags_ephemeral_drivers(): void
    {
        $this->assertStringContainsString("env('SESSION_DRIVER', 'database')", file_get_contents(config_path('session.php')));
        $this->assertStringContainsString('php artisan ena:runtime', file_get_contents(base_path('pre-deploy.sh')));

        Schema::create('sessions', function ($table) { $table->string('id')->primary(); });
        config(['session.driver' => 'database']);
        Artisan::call('ena:runtime');
        $out = Artisan::output();
        $this->assertStringContainsString('"session_driver":"database"', $out);
        $this->assertStringContainsString('"sessions_survive_deploy":true', $out);

        config(['session.driver' => 'file']);
        Artisan::call('ena:runtime');
        $out = Artisan::output();
        $this->assertStringContainsString('"sessions_survive_deploy":false', $out);
        $this->assertStringContainsString('usar SESSION_DRIVER=database', $out);
    }

    public function test_spa_sends_expired_sessions_and_419_to_login_with_spanish_notice(): void
    {
        $bootstrap = file_get_contents(resource_path('assets/js/bootstrap.js'));
        $this->assertMatchesRegularExpression("/status === 419\\)\\s*\\{[^}]*sessionEnded', 'no-guardado'/", $bootstrap);
        $this->assertStringContainsString("store.dispatch('auth/sessionEnded', 'sesion')", $bootstrap);
        $this->assertStringNotContainsString("'Unauthorized',", $bootstrap);
        $actions = file_get_contents(resource_path('assets/js/store/modules/auth/actions.js'));
        $this->assertStringContainsString("window.location.assign('/login?aviso='", $actions);
        $login = file_get_contents(resource_path('assets/js/views/auth/Login.vue'));
        $this->assertStringContainsString('el último cambio no se guardó', $login);
        $this->assertStringContainsString('Tu sesión terminó', $login);
    }
}
