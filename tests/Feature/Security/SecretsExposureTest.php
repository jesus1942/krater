<?php

use Crater\Models\FileDisk;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/**
 * Regresion: ningun endpoint devuelve secretos en texto plano.
 *
 * Estos son los primeros tests del proyecto que verifican que algo NO pase.
 * La suite existente tiene 245 casos y ni una sola asercion de 401, 403 o de
 * ausencia de datos sensibles: todos los tests entran como super admin y
 * comprueban el camino feliz.
 *
 * Los dos agujeros que cubren estos tests son reales y estuvieron en
 * produccion:
 *
 *  - GET /api/v1/mail/config devolvia `mail_password`, `mail_ses_secret` y
 *    `mail_mailgun_secret` en claro a cualquier usuario administrador.
 *  - GET /api/v1/disks devolvia los modelos FileDisk completos, incluida la
 *    columna `credentials` con las claves de S3, Spaces y Dropbox sin cifrar.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $this->admin = User::where('role', 'super admin')->first();
    $this->withHeaders(['company' => $this->admin->company_id]);

    Sanctum::actingAs($this->admin, ['*']);
});

it('no devuelve la contrasena de correo en claro', function () {
    config([
        'mail.password' => 'contrasena-smtp-secreta',
        'services.mailgun.secret' => 'key-mailgun-secreta',
        'services.ses.secret' => 'ses-secreta',
        'services.ses.key' => 'ses-key-secreta',
    ]);

    $respuesta = getJson('/api/v1/mail/config');

    $respuesta->assertOk();

    // Ninguno de los valores aparece en ninguna parte del cuerpo.
    foreach ([
        'contrasena-smtp-secreta',
        'key-mailgun-secreta',
        'ses-secreta',
        'ses-key-secreta',
    ] as $secreto) {
        expect($respuesta->content())->not->toContain($secreto);
    }
});

it('devuelve el correo enmascarado, no vacio, para que se note que hay algo cargado', function () {
    config(['mail.password' => 'unaClaveLarga1234']);

    getJson('/api/v1/mail/config')
        ->assertOk()
        ->assertJsonFragment(['mail_password' => '********1234']);
});

it('no confunde ausencia de valor con valor oculto', function () {
    config(['mail.password' => null]);

    getJson('/api/v1/mail/config')
        ->assertOk()
        ->assertJsonFragment(['mail_password' => '']);
});

it('no devuelve las credenciales de los discos por la API', function () {
    FileDisk::create([
        'name' => 'disco de prueba',
        'driver' => 's3',
        'type' => FileDisk::DISK_TYPE_REMOTE,
        'set_as_default' => false,
        'credentials' => [
            'key' => 'AKIA-CLAVE-SECRETA',
            'secret' => 'secreto-de-s3-muy-secreto',
        ],
    ]);

    $respuesta = getJson('/api/v1/disks');

    $respuesta->assertOk();
    expect($respuesta->content())->not->toContain('AKIA-CLAVE-SECRETA');
    expect($respuesta->content())->not->toContain('secreto-de-s3-muy-secreto');
    expect($respuesta->content())->not->toContain('credentials');
});

it('sigue pudiendo leer las credenciales internamente aunque no las serialice', function () {
    // `$hidden` afecta la serializacion, no el acceso al atributo. Si esto se
    // rompiera, FileDisk::setConfig() dejaria de poder configurar el disco y la
    // subida de archivos fallaria en silencio.
    $disco = FileDisk::create([
        'name' => 'otro disco',
        'driver' => 's3',
        'type' => FileDisk::DISK_TYPE_REMOTE,
        'set_as_default' => false,
        'credentials' => ['key' => 'valor-interno'],
    ]);

    expect(json_decode($disco->fresh()['credentials'], true))
        ->toHaveKey('key', 'valor-interno');
});
