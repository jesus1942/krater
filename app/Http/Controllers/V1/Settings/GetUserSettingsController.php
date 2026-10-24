<?php

namespace Crater\Http\Controllers\V1\Settings;

use Auth;
use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\GetSettingsRequest;

class GetUserSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\GetSettingsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(GetSettingsRequest $request)
    {
        $user = Auth::user();

        $settings = $user->getSettings($request->settings);
        if (in_array('language', $request->settings, true)) {
            // Mi perfil muestra el mismo idioma efectivo que bootstrap, sin escribir preferencias.
            $settings['language'] = $user->preferredLocale();
        }

        return response()->json($settings);
    }
}
