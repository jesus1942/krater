<?php

namespace Crater\Http\Controllers\V1\Family;

use Crater\Http\Controllers\Controller;
use Crater\Models\FamilyMember;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FamilyMembersController extends Controller
{
    public function __construct()
    {
        $this->middleware('tenant');
    }

    public function index(Request $request, AccessManager $access)
    {
        $companyId = (int) $request->header('company');
        $user = $request->user();
        $levelId = TenantContext::schoolLevelId();
        $institutionWide = $access->isTotalAdmin($user);

        $query = FamilyMember::query()
            ->where('company_id', $companyId)
            ->with(['students' => function ($query) use ($institutionWide) {
                if ($institutionWide) {
                    $query->withoutGlobalScope('school_level');
                }

                $query->select(
                    'students.id',
                    'students.first_name',
                    'students.last_name',
                    'students.school_level_id',
                    'students.level',
                    'students.grade',
                    'students.division'
                );
            }])
            ->when(! $institutionWide && $levelId, function ($query) {
                $query->whereHas('students');
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('dni', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name');

        $members = $query->get();

        return response()->json([
            'data' => $members,
            'meta' => [
                'total' => $members->count(),
                'institution_wide' => $institutionWide,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->header('company');
        $data = $this->validated($request, $companyId);
        $data['company_id'] = $companyId;
        $data['dni'] = $this->normalizeDni($data['dni'] ?? null) ?: null;

        if (! empty($data['dni'])) {
            $existing = FamilyMember::where('company_id', $companyId)
                ->where('dni', $data['dni'])
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Ya existe un familiar con ese DNI.',
                    'errors' => ['dni' => ['Ya existe un familiar con ese DNI.']],
                ], 422);
            }
        }

        $member = FamilyMember::create($data);

        return response()->json(['data' => $member, 'success' => true], 201);
    }

    public function update(Request $request, FamilyMember $familyMember)
    {
        $companyId = (int) $request->header('company');
        abort_unless((int) $familyMember->company_id === $companyId, 404);

        $data = $this->validated($request, $companyId, $familyMember->id);
        $data['dni'] = $this->normalizeDni($data['dni'] ?? null) ?: null;
        $familyMember->update($data);

        return response()->json([
            'data' => $familyMember->fresh('students'),
            'success' => true,
        ]);
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'dni' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('family_members', 'dni')
                    ->where(function ($query) use ($companyId) {
                        return $query->where('company_id', $companyId);
                    })
                    ->ignore($ignoreId),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function normalizeDni($dni): string
    {
        if ($dni === null) {
            return '';
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $dni)));
    }
}
