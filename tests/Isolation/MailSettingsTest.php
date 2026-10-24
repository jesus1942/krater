<?php

namespace Tests\Isolation;

use Crater\Models\User;
use Crater\Services\Mail\MailSettings;
use Crater\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesApplication;

/** Fila C: correo configurable desde administracion total, secretos cifrados. */
class MailSettingsTest extends TestCase
{
    use CreatesApplication;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'queue.default' => 'sync', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite'); TenantContext::clear();
        Artisan::call('migrate', ['--force' => true]);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        DB::table('companies')->insert(['id' => 1, 'name' => 'Institucion ficticia', 'unique_hash' => 'mail-fixture']);
        foreach (['carbon_date_format' => 'd/m/Y', 'moment_date_format' => 'DD/MM/YYYY', 'language' => 'es', 'currency' => 1,
            'time_zone' => 'UTC', 'fiscal_year' => '1-12'] as $key => $value) {
            DB::table('company_settings')->insert(['id' => DB::table('company_settings')->max('id') + 1, 'company_id' => 1, 'option' => $key, 'value' => $value]);
        }
        DB::table('school_levels')->insert(['id' => 1, 'company_id' => 1, 'code' => 'primary', 'name' => 'Primario', 'enabled' => true]);
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);
        $this->admin = $this->account('total', 'total_admin', null);
        $this->as($this->admin, null);
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
        $this->actingAs($user->fresh(), 'web');

        return $this->withHeaders(['company' => '1', 'school-level' => $level === null ? '' : (string) $level]);
    }

    private function smtp(array $extra = []): array
    {
        return $extra + ['mail_driver' => 'smtp', 'mail_host' => 'smtp.example.invalid', 'mail_port' => 587,
            'mail_username' => 'escuela@example.invalid', 'mail_password' => 'clave-smtp-ficticia-9876',
            'mail_encryption' => 'tls', 'from_name' => 'Escuela ficticia', 'from_mail' => 'no-responder@example.invalid'];
    }

    public function test_secrets_are_stored_encrypted_and_never_returned_in_clear(): void
    {
        $this->postJson('/api/v1/mail/config', $this->smtp())->assertOk();

        $row = DB::table('mail_settings')->first();
        $this->assertStringNotContainsString('clave-smtp-ficticia', $row->password_encrypted);
        $this->assertSame('clave-smtp-ficticia-9876', Crypt::decryptString($row->password_encrypted));
        $response = $this->getJson('/api/v1/mail/config')->assertOk();
        $this->assertStringNotContainsString('clave-smtp-ficticia', $response->getContent());
        $this->assertSame(['********9876', 'smtp.example.invalid', true],
            [$response->json('mail_password'), $response->json('mail_host'), $response->json('stored_in_database')]);
        // La auditoria registra que cambio el secreto, nunca su valor.
        $audit = DB::table('audit_logs')->where('action', 'mail_settings.updated')->first();
        $this->assertStringNotContainsString('clave-smtp-ficticia', (string) $audit->new_values);
        $this->assertSame(['mail_password'], json_decode($audit->new_values, true)['secrets_changed']);
    }

    public function test_masked_or_empty_secret_keeps_the_stored_one_and_new_value_replaces_it(): void
    {
        $this->postJson('/api/v1/mail/config', $this->smtp())->assertOk();
        $this->postJson('/api/v1/mail/config', $this->smtp(['mail_password' => '********9876', 'mail_host' => 'smtp2.example.invalid']))->assertOk();
        $this->postJson('/api/v1/mail/config', $this->smtp(['mail_password' => '']))->assertOk();
        $this->assertSame('clave-smtp-ficticia-9876', Crypt::decryptString(DB::table('mail_settings')->value('password_encrypted')));
        $this->postJson('/api/v1/mail/config', $this->smtp(['mail_password' => 'clave-nueva-ficticia-1111']))->assertOk();
        $this->assertSame('clave-nueva-ficticia-1111', Crypt::decryptString(DB::table('mail_settings')->value('password_encrypted')));
        $this->assertSame(1, DB::table('mail_settings')->count());
        // Mailgun sin secreto guardado no se acepta.
        $this->postJson('/api/v1/mail/config', ['mail_driver' => 'mailgun', 'mail_mailgun_domain' => 'mg.example.invalid',
            'mail_mailgun_endpoint' => 'api.mailgun.net', 'from_name' => 'Escuela', 'from_mail' => 'a@example.invalid'])->assertStatus(422);
    }

    public function test_stored_settings_drive_real_delivery_except_in_staging(): void
    {
        $this->postJson('/api/v1/mail/config', $this->smtp())->assertOk();
        config(['mail.driver' => 'log', 'mail.password' => null, 'mail.host' => null]);
        app(MailSettings::class)->apply();
        $this->assertSame(['smtp', 'smtp.example.invalid', 'clave-smtp-ficticia-9876', 'no-responder@example.invalid'],
            [config('mail.driver'), config('mail.host'), config('mail.password'), config('mail.from.address')]);

        $this->app['env'] = 'staging';
        config(['mail.driver' => 'log']);
        app(MailSettings::class)->apply();
        $this->assertSame('log', config('mail.driver'));
        $this->assertTrue($this->getJson('/api/v1/mail/config')->json('delivery_disabled'));

        Mail::fake();
        $this->postJson('/api/v1/mail/test', ['to' => 'destino@example.invalid', 'subject' => 'Prueba', 'message' => 'Hola'])->assertOk();
        Mail::assertSent(\Crater\Mail\TestMail::class);
    }

    public function test_only_total_admin_configures_mail_and_a_rotated_key_does_not_break_the_app(): void
    {
        $director = $this->account('director', 'general_director', null);
        $this->as($director, null);
        $this->getJson('/api/v1/mail/config')->assertStatus(403);
        $this->postJson('/api/v1/mail/config', $this->smtp())->assertStatus(403);
        $this->assertSame(0, DB::table('mail_settings')->count());

        $this->as($this->admin, null);
        $this->postJson('/api/v1/mail/config', $this->smtp())->assertOk();
        DB::table('mail_settings')->update(['password_encrypted' => 'cifrado-con-otra-clave']);
        app(MailSettings::class)->apply();
        $this->assertNull(config('mail.password'));
        $this->assertSame('', $this->getJson('/api/v1/mail/config')->assertOk()->json('mail_password'));
        // Guardar ya no reescribe el .env del contenedor.
        $this->assertStringNotContainsString('saveMailVariables',
            file_get_contents(app_path('Http/Controllers/V1/Settings/MailConfigurationController.php')));
    }
}
