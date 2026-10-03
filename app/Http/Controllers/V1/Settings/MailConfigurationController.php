<?php

namespace Crater\Http\Controllers\V1\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\MailEnvironmentRequest;
use Crater\Mail\TestMail;
use Crater\Models\Setting;
use Crater\Space\EnvironmentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mail;

class MailConfigurationController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $environmentManager;

    /**
     * @param EnvironmentManager $environmentManager
     */
    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->environmentManager = $environmentManager;
    }

    /**
     *
     * @param MailEnvironmentRequest $request
     * @return JsonResponse
     */
    public function saveMailEnvironment(MailEnvironmentRequest $request)
    {
        $setting = Setting::getSetting('profile_complete');
        $results = $this->environmentManager->saveMailVariables($request);

        if ($setting !== 'COMPLETED') {
            Setting::setSetting('profile_complete', 4);
        }

        return response()->json($results);
    }

    /**
     * Devuelve la configuracion de correo con los secretos enmascarados.
     *
     * Antes este endpoint devolvia `mail_password`, `mail_mailgun_secret` y
     * `mail_ses_secret` en texto plano: cualquier usuario administrador recibia
     * la contrasena SMTP en la respuesta JSON.
     *
     * Ahora sale el enmascarado. El frontend lo muestra deshabilitado y, para
     * cambiar un secreto, manda el valor nuevo completo; nunca necesita leer el
     * actual.
     */
    public function getMailEnvironment()
    {
        $MailData = [
            'mail_driver' => config('mail.driver'),
            'mail_host' => config('mail.host'),
            'mail_port' => config('mail.port'),
            'mail_username' => config('mail.username'),
            'mail_password' => $this->mask(config('mail.password')),
            'mail_encryption' => config('mail.encryption'),
            'from_name' => config('mail.from.name'),
            'from_mail' => config('mail.from.address'),
            'mail_mailgun_endpoint' => config('services.mailgun.endpoint'),
            'mail_mailgun_domain' => config('services.mailgun.domain'),
            'mail_mailgun_secret' => $this->mask(config('services.mailgun.secret')),
            'mail_ses_key' => $this->mask(config('services.ses.key')),
            'mail_ses_secret' => $this->mask(config('services.ses.secret')),
        ];

        return response()->json($MailData);
    }

    /**
     * Enmascara un secreto dejando visibles solo los ultimos 4 caracteres.
     *
     * Devuelve cadena vacia si no hay valor configurado, para que el frontend pueda
     * distinguir entre "no hay nada cargado" y "hay algo que no te muestro".
     */
    protected function mask(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // No se revelan los primeros caracteres: el prefijo identifica al
        // proveedor y le da informacion util a quien esté probando.
        return str_repeat('*', 8).substr($value, -4);
    }

    /**
     *
     * @return JsonResponse
     */
    public function getMailDrivers()
    {
        $drivers = [
            'smtp',
            'mail',
            'sendmail',
            'mailgun',
            'ses',
        ];

        return response()->json($drivers);
    }

    public function testEmailConfig(Request $request)
    {
        $this->validate($request, [
            'to' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ]);

        Mail::to($request->to)->send(new TestMail($request->subject, $request->message));

        return response()->json([
            'success' => true,
        ]);
    }
}
