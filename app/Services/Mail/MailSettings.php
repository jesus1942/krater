<?php

namespace Crater\Services\Mail;

use Crater\Models\User;
use Crater\Services\Audit\Auditor;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Fila C: configuracion de correo de la institucion, guardada en la base.
 *
 * - Los secretos (clave SMTP, secreto de Mailgun, claves de SES) se cifran con
 *   APP_KEY y nunca salen en claro: la lectura los devuelve enmascarados.
 * - Guardar un secreto vacio o enmascarado conserva el actual.
 * - Se aplica en cada request sobre config('mail') y config('services').
 * - En staging el envio real queda desactivado: se mantiene el driver log.
 */
class MailSettings
{
    const DRIVERS = ['smtp', 'mailgun', 'ses', 'sendmail', 'mail'];
    const SECRETS = ['mail_password' => 'password_encrypted', 'mail_mailgun_secret' => 'mailgun_secret_encrypted',
        'mail_ses_key' => 'ses_key_encrypted', 'mail_ses_secret' => 'ses_secret_encrypted'];
    const MASK = '********';

    public function stored(): ?object
    {
        try {
            return DB::table('mail_settings')->orderBy('id')->first();
        } catch (Throwable $e) {
            return null; // sin base o sin tabla (build, instalacion): se usa el .env
        }
    }

    /** Lo que ve la pantalla: valores vigentes con secretos enmascarados. */
    public function masked(): array
    {
        $row = $this->stored();
        $secret = fn ($column, $fallback) => $this->mask($row ? $this->decrypt($row->$column) : $fallback);

        return [
            'mail_driver' => $row->driver ?? config('mail.driver'),
            'mail_host' => $row->host ?? config('mail.host'),
            'mail_port' => $row->port ?? config('mail.port'),
            'mail_username' => $row->username ?? config('mail.username'),
            'mail_password' => $secret('password_encrypted', config('mail.password')),
            'mail_encryption' => $row->encryption ?? config('mail.encryption'),
            'from_name' => $row->from_name ?? config('mail.from.name'),
            'from_mail' => $row->from_address ?? config('mail.from.address'),
            'mail_mailgun_endpoint' => $row->mailgun_endpoint ?? config('services.mailgun.endpoint'),
            'mail_mailgun_domain' => $row->mailgun_domain ?? config('services.mailgun.domain'),
            'mail_mailgun_secret' => $secret('mailgun_secret_encrypted', config('services.mailgun.secret')),
            'mail_ses_key' => $secret('ses_key_encrypted', config('services.ses.key')),
            'mail_ses_secret' => $secret('ses_secret_encrypted', config('services.ses.secret')),
            'stored_in_database' => (bool) $row,
            'delivery_disabled' => app()->environment('staging'),
        ];
    }

    public function save(User $actor, array $data): void
    {
        $driver = $data['mail_driver'] ?? null;
        if (! in_array($driver, self::DRIVERS, true)) {
            throw ValidationException::withMessages(['mail_driver' => 'Elegí un servicio de correo válido.']);
        }
        DB::transaction(function () use ($actor, $data, $driver) {
            $row = DB::table('mail_settings')->orderBy('id')->lockForUpdate()->first();
            $values = [
                'driver' => $driver, 'host' => $data['mail_host'] ?? null,
                'port' => isset($data['mail_port']) && $data['mail_port'] !== '' ? (int) $data['mail_port'] : null,
                'username' => $data['mail_username'] ?? null, 'encryption' => $data['mail_encryption'] ?? null,
                'from_address' => $data['from_mail'], 'from_name' => $data['from_name'],
                'mailgun_domain' => $data['mail_mailgun_domain'] ?? null, 'mailgun_endpoint' => $data['mail_mailgun_endpoint'] ?? null,
                'ses_region' => $data['mail_ses_region'] ?? null,
                'updated_by' => $actor->id, 'updated_at' => now(),
            ];
            $changed = [];
            foreach (self::SECRETS as $field => $column) {
                $value = $data[$field] ?? null;
                if ($value === null || $value === '' || strpos($value, self::MASK) === 0) {
                    $values[$column] = $row->$column ?? null; // se conserva el actual
                } else {
                    $values[$column] = Crypt::encryptString($value);
                    $changed[] = $field;
                }
            }
            $required = ['mailgun' => ['mailgun_secret_encrypted'], 'ses' => ['ses_key_encrypted', 'ses_secret_encrypted']][$driver] ?? [];
            foreach ($required as $column) {
                if (! $values[$column]) {
                    throw ValidationException::withMessages([array_search($column, self::SECRETS, true) => 'Falta cargar este secreto.']);
                }
            }
            if ($row) {
                DB::table('mail_settings')->where('id', $row->id)->update($values);
            } else {
                DB::table('mail_settings')->insert($values + ['created_at' => now()]);
            }
            // Auditoria sin secretos: solo que campos cambiaron.
            app(Auditor::class)->record('mail_settings.updated', $actor, ['type' => 'mail_settings', 'id' => 1,
                'company_id' => $actor->company_id, 'school_level_id' => null], [],
                ['driver' => $driver, 'host' => $values['host'], 'from_address' => $values['from_address'], 'secrets_changed' => $changed],
                \Crater\Models\AuditLog::SEVERITY_HIGH);
        });
        $this->apply();
    }

    /** Pisa la configuracion de Laravel con la guardada en la base. */
    public function apply(): void
    {
        $row = $this->stored();
        if (! $row) {
            return;
        }
        config([
            'mail.host' => $row->host, 'mail.port' => $row->port, 'mail.username' => $row->username,
            'mail.password' => $this->decrypt($row->password_encrypted), 'mail.encryption' => $row->encryption,
            'mail.from.address' => $row->from_address, 'mail.from.name' => $row->from_name,
            'services.mailgun.domain' => $row->mailgun_domain, 'services.mailgun.endpoint' => $row->mailgun_endpoint,
            'services.mailgun.secret' => $this->decrypt($row->mailgun_secret_encrypted),
            'services.ses.key' => $this->decrypt($row->ses_key_encrypted), 'services.ses.secret' => $this->decrypt($row->ses_secret_encrypted),
            'services.ses.region' => $row->ses_region ?: config('services.ses.region'),
        ]);
        // Staging nunca envia correo real (CLAUDE.md): conserva su driver de log.
        if (! app()->environment('staging')) {
            config(['mail.driver' => $row->driver]);
        }
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }

    protected function decrypt(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // APP_KEY cambio: el secreto guardado ya no sirve y hay que volver a cargarlo.
            Log::warning('No se pudo descifrar un secreto de correo guardado; hay que volver a cargarlo.');

            return null;
        }
    }

    protected function mask(?string $value): string
    {
        return $value === null || $value === '' ? '' : self::MASK.substr($value, -4);
    }
}
