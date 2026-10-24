<?php

namespace Tests\Isolation;

use Crater\Models\User;
use Crater\Services\Site\SiteContent;
use Crater\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** Sitio publico editable: la administracion total edita todo, cada direccion su nivel. */
class SiteSettingsTest extends TestCase
{
    use CreatesApplication;

    private User $admin;

    /** JPEG minimo valido (1x1), sin depender de GD. */
    const JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'queue.default' => 'sync', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite'); TenantContext::clear(); SiteContent::flush();
        Artisan::call('migrate', ['--force' => true]);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        DB::table('companies')->insert(['id' => 1, 'name' => 'Institucion ficticia', 'unique_hash' => 'site-fixture']);
        foreach (['carbon_date_format' => 'd/m/Y', 'moment_date_format' => 'DD/MM/YYYY', 'language' => 'es', 'currency' => 1,
            'time_zone' => 'UTC', 'fiscal_year' => '1-12'] as $key => $value) {
            DB::table('company_settings')->insert(['id' => DB::table('company_settings')->max('id') + 1, 'company_id' => 1, 'option' => $key, 'value' => $value]);
        }
        foreach ([1 => 'primary', 2 => 'secondary', 3 => 'tertiary'] as $id => $code) {
            DB::table('school_levels')->insert(['id' => $id, 'company_id' => 1, 'code' => $code, 'name' => ucfirst($code), 'enabled' => true]);
        }
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);
        $this->admin = $this->account('total', 'total_admin', null);
    }

    private function account(string $name, string $role, ?int $level): User
    {
        $user = User::create(['company_id' => 1, 'name' => $name, 'email' => $name.'@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'staff', 'currency_id' => 1]);
        DB::table('role_user')->insert(['company_id' => 1, 'user_id' => $user->id, 'school_level_id' => $level,
            'role_id' => DB::table('roles')->where('company_id', 1)->where('name', $role)->value('id')]);

        return $user;
    }

    private function as(User $user, ?int $level)
    {
        auth()->forgetGuards();
        TenantContext::clear(); SiteContent::flush();
        $this->actingAs($user->fresh(), 'web');

        return $this->withHeaders(['company' => '1', 'school-level' => $level === null ? '' : (string) $level]);
    }

    private function jpeg(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'site').'.jpg';
        file_put_contents($path, base64_decode(self::JPEG));

        return new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true);
    }

    public function test_total_admin_edits_the_institution_photo_and_every_level(): void
    {
        $this->as($this->admin, null);
        $settings = $this->getJson('/api/v1/site/settings')->assertOk()->json();
        $this->assertTrue($settings['can_edit_institution']);
        $this->assertSame(['primario', 'secundario', 'terciario'], array_keys($settings['levels']));

        $this->putJson('/api/v1/site/institution', ['phone' => '0280 444 5555', 'tagline' => 'Educar en la Patagonia.', 'email' => 'no-es-correo'])
            ->assertStatus(422);
        $this->putJson('/api/v1/site/institution', ['phone' => '0280 444 5555', 'tagline' => 'Educar en la Patagonia.', 'address' => ''])->assertOk();
        $this->putJson('/api/v1/site/levels/secundario', ['phone' => '0280 466 7777', 'summary' => 'Secundario renovado.'])->assertOk();
        $this->post('/api/v1/site/images/hero', ['image' => $this->jpeg(), 'alt' => 'Frente de la escuela'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('hero_image.alt', 'Frente de la escuela');
        $this->assertTrue(DB::table('audit_logs')->where('action', 'site.image_updated')->exists());

        SiteContent::flush();
        $home = $this->get('/')->assertOk();
        $home->assertSee('Educar en la Patagonia.')->assertSee('0280 444 5555')->assertSee('De los Avellanos 4094')
            ->assertSee('/sitio/imagen/hero?v=', false);
        $this->get('/secundario')->assertOk()->assertSee('Secundario renovado.')->assertSee('0280 466 7777');
        // Un nivel sin telefono propio usa el de la institucion.
        $this->get('/primario')->assertOk()->assertSee('0280 444 5555');

        $image = $this->get('/sitio/imagen/hero')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame(base64_decode(self::JPEG), $image->getContent());
        $this->get('/sitio/imagen/hero', ['If-None-Match' => $image->headers->get('ETag')])->assertStatus(304);
        $this->get('/sitio/imagen/level.primario')->assertStatus(404);

        $this->as($this->admin, null);
        $this->deleteJson('/api/v1/site/images/hero')->assertOk()->assertJsonPath('hero_image', null);
    }

    public function test_level_direction_edits_only_its_level_page(): void
    {
        $directora = $this->account('directora', 'level_director', 1);
        $this->as($directora, 1);
        $settings = $this->getJson('/api/v1/site/settings')->assertOk()->json();
        $this->assertFalse($settings['can_edit_institution']);
        $this->assertSame(['primario'], array_keys($settings['levels']));

        $this->putJson('/api/v1/site/levels/primario', ['tagline' => 'Aprender jugando.', 'email' => 'primario@example.invalid'])->assertOk();
        $this->post('/api/v1/site/images/level.primario', ['image' => $this->jpeg()], ['Accept' => 'application/json'])->assertOk();
        $this->putJson('/api/v1/site/levels/secundario', ['tagline' => 'x'])->assertStatus(403);
        $this->putJson('/api/v1/site/institution', ['phone' => '1'])->assertStatus(403);
        $this->post('/api/v1/site/images/hero', ['image' => $this->jpeg()], ['Accept' => 'application/json'])->assertStatus(403);
        $this->post('/api/v1/site/images/level.terciario', ['image' => $this->jpeg()], ['Accept' => 'application/json'])->assertStatus(403);

        SiteContent::flush();
        $this->get('/primario')->assertOk()->assertSee('Aprender jugando.')->assertSee('primario@example.invalid')
            ->assertSee('/sitio/imagen/level.primario?v=', false);

        // Un preceptor no edita el sitio.
        $this->as($this->account('preceptor', 'preceptor', 1), 1);
        $this->getJson('/api/v1/site/settings')->assertStatus(403);
    }

    public function test_tertiary_page_shows_the_career_and_the_meeting_only_when_scheduled(): void
    {
        $page = $this->get('/terciario')->assertOk();
        $page->assertSee('Instituto de Nivel Superior Técnico Nueva Austral N.º 1831')->assertSee('Diagnosticar fallas.')
            ->assertSee('Lunes a viernes, de 18 a 22.30 h (mayormente)')->assertSee('Primer año: marzo de 2027')
            ->assertSee('ena-logo-institucional.svg', false)->assertDontSee('Reunión informativa por Zoom');
        $this->get('/primario')->assertOk()->assertDontSee('Perfil del egresado');

        $this->as($this->admin, null);
        $this->putJson('/api/v1/site/levels/terciario', ['meeting_link' => 'http://zoom.example.invalid'])->assertStatus(422);
        $this->putJson('/api/v1/site/levels/terciario', ['meeting_date' => 'Jueves 12 de noviembre, 18.30 h',
            'meeting_link' => 'https://zoom.example.invalid/j/1'])->assertOk();
        $this->get('/terciario')->assertOk()->assertSee('Reunión informativa por Zoom')
            ->assertSee('Jueves 12 de noviembre, 18.30 h')->assertSee('https://zoom.example.invalid/j/1', false);
    }

    public function test_only_images_are_accepted(): void
    {
        $this->as($this->admin, null);
        $path = tempnam(sys_get_temp_dir(), 'site').'.txt';
        file_put_contents($path, 'no soy una foto');
        $this->post('/api/v1/site/images/hero', ['image' => new UploadedFile($path, 'nota.txt', 'text/plain', null, true)],
            ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, DB::table('site_images')->count());
    }
}
