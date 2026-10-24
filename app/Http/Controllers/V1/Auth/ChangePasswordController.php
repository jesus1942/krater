<?php

namespace Crater\Http\Controllers\V1\Auth;

use Crater\Http\Controllers\Controller;
use Crater\Services\Audit\Auditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Cambio de la propia contraseña; obligatorio tras una contraseña inicial (fila E1). */
class ChangePasswordController extends Controller
{
    public function __invoke(Request $request, Auditor $auditor)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:120', 'confirmed', 'different:current_password'],
        ]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
        }
        $user->password = $data['password'];
        $user->forceFill(['must_change_password' => false])->save();
        $auditor->record('user.password_changed', $user, ['type' => get_class($user), 'id' => $user->id,
            'company_id' => $user->company_id, 'school_level_id' => null]);

        return response()->json(['success' => true]);
    }
}
