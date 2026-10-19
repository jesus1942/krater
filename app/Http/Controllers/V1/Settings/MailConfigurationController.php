<?php

namespace Crater\Http\Controllers\V1\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\MailEnvironmentRequest;
use Crater\Mail\TestMail;
use Crater\Models\Setting;
use Crater\Services\Mail\MailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mail;

class MailConfigurationController extends Controller
{
    /**
     * Fila C: la configuracion se guarda en la base con los secretos cifrados
     * (MailSettings). Ya no se escribe el .env del contenedor, que se perdia en
     * cada deploy.
     */
    public function saveMailEnvironment(MailEnvironmentRequest $request, MailSettings $mail)
    {
        $mail->save($request->user(), $request->all());
        if (Setting::getSetting('profile_complete') !== 'COMPLETED') {
            Setting::setSetting('profile_complete', 4);
        }

        return response()->json(['success' => 'mail_variables_save_successfully']);
    }

    /** Configuracion vigente con los secretos enmascarados; nunca en claro. */
    public function getMailEnvironment(MailSettings $mail)
    {
        return response()->json($mail->masked());
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
