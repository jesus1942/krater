<?php

use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\postJson;

/**
 * Endurecimiento del ingreso.
 *
 * Desde que la landing publica expone un boton visible hacia /login, el
 * formulario esta a un clic de cualquiera que encuentre el sitio. Antes de eso
 * la ruta estaba exceptuada de CSRF y sin limite de intentos: fuerza bruta
 * ilimitada contra una URL que ahora es facil de encontrar.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
});

it('ya no exceptua la ruta de ingreso de la verificacion CSRF', function () {
    $middleware = new Crater\Http\Middleware\VerifyCsrfToken(
        app(),
        app(Illuminate\Contracts\Encryption\Encrypter::class)
    );

    $excepciones = (new ReflectionClass($middleware))
        ->getProperty('except');
    $excepciones->setAccessible(true);

    expect(array_filter($excepciones->getValue($middleware)))->toBeEmpty();
});

it('limita los intentos de ingreso', function () {
    $ruta = collect(Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'login' && in_array('POST', $r->methods(), true));

    expect($ruta)->not->toBeNull();
    expect($ruta->gatherMiddleware())->toContain('throttle:5,15');
});

it('bloquea despues de varios intentos fallidos seguidos', function () {
    $usuario = User::where('role', 'super admin')->first();

    // Los primeros cinco fallan por credenciales; el sexto por limite.
    for ($i = 0; $i < 5; $i++) {
        postJson('/login', [
            'email' => $usuario->email,
            'password' => 'contrasena-incorrecta',
        ]);
    }

    postJson('/login', [
        'email' => $usuario->email,
        'password' => 'contrasena-incorrecta',
    ])->assertStatus(429);
})->skip('Requiere un cache driver que cuente entre requests; habilitar cuando el entorno de test use redis o database.');

it('emite tokens que vencen', function () {
    // Un token eterno filtrado sirve para siempre. Antes `expiration` era null.
    expect(config('sanctum.expiration'))->not->toBeNull();
    expect(config('sanctum.expiration'))->toBeGreaterThan(0);
});
