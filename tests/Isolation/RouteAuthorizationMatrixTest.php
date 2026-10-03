<?php

namespace Tests\Isolation;

use Crater\Enums\Permission;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Route;
use Tests\CreatesApplication;

class RouteAuthorizationMatrixTest extends TestCase
{
    use CreatesApplication;

    private function violations(): array
    {
        // Perfil propio: no elige ni modifica otra cuenta. Bootstrap y selector
        // deben funcionar antes de seleccionar nivel. Los catalogos son constantes.
        $personal = [
            'POST api/v1/auth/logout', 'GET api/v1/auth/check', 'GET api/v1/bootstrap',
            'GET api/v1/me', 'PUT api/v1/me', 'GET api/v1/me/settings', 'PUT api/v1/me/settings',
            'POST api/v1/me/upload-avatar', 'GET api/v1/school-levels',
            'GET api/v1/currencies', 'GET api/v1/timezones', 'GET api/v1/date/formats',
            'GET api/v1/fiscal/years', 'GET api/v1/languages',
        ];
        // Publicas necesarias para ingresar, recuperar la clave e instalar.
        $public = [
            'GET api/v1/app/version', 'GET api/v1/countries', 'POST api/v1/auth/login',
            'POST api/v1/auth/password/email', 'POST api/v1/auth/reset/password',
        ];
        $installation = [
            'GET api/v1/onboarding/wizard-step', 'POST api/v1/onboarding/wizard-step',
            'GET api/v1/onboarding/requirements', 'GET api/v1/onboarding/permissions',
            'POST api/v1/onboarding/database/config', 'GET api/v1/onboarding/database/config',
            'PUT api/v1/onboarding/set-domain', 'POST api/v1/onboarding/login', 'POST api/v1/onboarding/finish',
        ];
        $errors = [];
        foreach (Route::getRoutes() as $route) {
            if (strpos($route->uri(), 'api/v1/') !== 0) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' '.$route->uri();
                if (in_array($key, $public, true)) {
                    continue;
                }
                if (in_array($key, $installation, true)) {
                    if (! in_array('redirect-if-installed', $middleware, true)) {
                        $errors[] = $key.' sin bloqueo de instalacion';
                    }
                    continue;
                }
                if (! in_array('auth:sanctum', $middleware, true) || ! in_array('active-account', $middleware, true)) {
                    $errors[] = $key.' sin autenticacion/cuenta activa';
                }
                if (in_array($key, $personal, true)) {
                    continue;
                }
                $permissions = array_values(array_filter($middleware, fn ($m) => strpos($m, 'permission:') === 0));
                if (! in_array('tenant', $middleware, true) || empty($permissions)) {
                    $errors[] = $key.' sin tenant + permission';
                }
                foreach ($permissions as $requirement) {
                    foreach (explode(',', substr($requirement, strlen('permission:'))) as $permission) {
                        if (! in_array($permission, Permission::all(), true)) {
                            $errors[] = $key.' permiso inexistente '.$permission;
                        }
                    }
                }
            }
        }

        return $errors;
    }

    public function test_all_routes_have_explicit_authorization_or_documented_exception(): void
    {
        $this->assertSame([], $this->violations());
    }

    public function test_matrix_detects_a_new_authenticated_route_without_permission(): void
    {
        Route::get('api/v1/forgotten-guard', fn () => 'x')->middleware(['auth:sanctum', 'active-account', 'tenant']);
        $this->assertContains('GET api/v1/forgotten-guard sin tenant + permission', $this->violations());
    }

    public function test_matrix_detects_an_unlisted_public_route(): void
    {
        Route::get('api/v1/forgotten-auth', fn () => 'x');
        $this->assertContains('GET api/v1/forgotten-auth sin autenticacion/cuenta activa', $this->violations());
    }
}
