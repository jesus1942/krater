<?php

namespace Crater\Http\Controllers\V1\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Models\SchoolLevel;
use Illuminate\Http\Request;

class SchoolLevelsController extends Controller
{
    public function index(Request $request)
    {
        $levels = SchoolLevel::where('company_id', $request->header('company'))
            ->orderByRaw("CASE code WHEN 'primary' THEN 1 WHEN 'secondary' THEN 2 WHEN 'tertiary' THEN 3 ELSE 4 END")
            ->get();

        return response()->json(['levels' => $levels]);
    }

    public function update(Request $request, SchoolLevel $schoolLevel)
    {
        abort_unless((int) $schoolLevel->company_id === (int) $request->header('company'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'cue' => ['nullable', 'string', 'max:50'],
            'jurisdiction_code' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'resolution_number' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'billing_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'director_name' => ['nullable', 'string', 'max:150'],
            'secretary_name' => ['nullable', 'string', 'max:150'],
            'accounting_contact' => ['nullable', 'string', 'max:150'],
            'enabled' => ['boolean'],
        ]);

        $schoolLevel->update($data);

        return response()->json(['level' => $schoolLevel->fresh(), 'success' => true]);
    }
}
