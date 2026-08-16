<?php

namespace Crater\Http\Controllers\V1\Student;

use Crater\Http\Controllers\Controller;
use Crater\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FamilyMembersController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->header('company');
        $query = FamilyMember::query()
            ->where('company_id', $companyId)
            ->with(['students' => function ($query) use ($companyId) {
                $query->where('students.company_id', $companyId)
                    ->select('students.id', 'students.first_name', 'students.last_name', 'students.dni');
            }]);

        if ($request->filled('dni')) {
            $query->where('dni', $this->normalizeDni($request->get('dni')));
        } elseif ($request->filled('search')) {
            $search = trim($request->get('search'));
            $normalized = $this->normalizeDni($search);
            $query->where(function ($query) use ($search, $normalized) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
                if ($normalized !== '') {
                    $query->orWhere('dni', 'like', '%'.$normalized.'%');
                }
            });
        }

        return response()->json([
            'family_members' => $query->orderBy('name')->limit(50)->get(),
        ]);
    }

    public function show(Request $request, FamilyMember $familyMember)
    {
        $this->ensureCompany($request, $familyMember);

        return response()->json([
            'family_member' => $familyMember->load('students:id,first_name,last_name,dni'),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->header('company');
        $data = $this->validatedData($request, $companyId);
        $data['company_id'] = $companyId;
        $data['dni'] = $this->normalizeDni($data['dni'] ?? null) ?: null;

        if (! empty($data['dni'])) {
            $existing = FamilyMember::where('company_id', $companyId)
                ->where('dni', $data['dni'])
                ->first();
            if ($existing) {
                $existing->update(array_filter($data, function ($value, $key) {
                    return $key !== 'company_id' && $value !== null && $value !== '';
                }, ARRAY_FILTER_USE_BOTH));

                return response()->json(['family_member' => $existing, 'matched' => true]);
            }
        }

        $familyMember = FamilyMember::create($data);

        return response()->json(['family_member' => $familyMember, 'matched' => false], 201);
    }

    public function update(Request $request, FamilyMember $familyMember)
    {
        $this->ensureCompany($request, $familyMember);
        $companyId = (int) $request->header('company');
        $data = $this->validatedData($request, $companyId, $familyMember->id);
        if (array_key_exists('dni', $data)) {
            $data['dni'] = $this->normalizeDni($data['dni']) ?: null;
        }
        $familyMember->update($data);

        return response()->json(['family_member' => $familyMember, 'success' => true]);
    }

    private function validatedData(Request $request, $companyId, $ignoreId = null)
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'dni' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('family_members')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                })->ignore($ignoreId),
            ],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function normalizeDni($dni)
    {
        if ($dni === null) {
            return '';
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $dni)));
    }

    private function ensureCompany(Request $request, FamilyMember $familyMember)
    {
        abort_unless((int) $familyMember->company_id === (int) $request->header('company'), 404);
    }
}
