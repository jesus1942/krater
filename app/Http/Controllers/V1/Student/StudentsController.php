<?php

namespace Crater\Http\Controllers\V1\Student;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\StudentRequest;
use Crater\Models\FamilyMember;
use Crater\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $companyId = $request->header('company');
        $limit = $request->get('limit', 15);

        $query = Student::with([
            'guardian:id,name,email,phone',
            'familyMembers',
        ])
            ->where('company_id', $companyId)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('dni', 'like', '%'.$search.'%');
                });
            })
            ->when($request->level, function ($query, $level) {
                $query->where('level', $level);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->school_year, function ($query, $schoolYear) {
                $query->where('school_year', $schoolYear);
            })
            ->orderBy('last_name')
            ->orderBy('first_name');

        $students = $limit === 'all' ? $query->get() : $query->paginate((int) $limit);

        return response()->json([
            'students' => $students,
            'summary' => [
                'total' => Student::where('company_id', $companyId)->count(),
                'active' => Student::where('company_id', $companyId)->where('status', 'active')->count(),
                'pending' => Student::where('company_id', $companyId)->where('status', 'pending')->count(),
            ],
        ]);
    }

    public function store(StudentRequest $request)
    {
        $companyId = (int) $request->header('company');
        $validated = $request->validated();
        $familyMembers = $validated['family_members'] ?? [];
        unset($validated['family_members']);

        if ($request->header('school-level')) {
            $validated['school_level_id'] = $request->header('school-level');
        }

        $student = DB::transaction(function () use ($validated, $familyMembers, $companyId) {
            $student = Student::create(array_merge($validated, [
                'company_id' => $companyId,
            ]));
            $this->syncFamilyMembers($student, $familyMembers, $companyId);

            return $student;
        });

        return response()->json([
            'student' => $this->loadStudentRelations($student),
            'success' => true,
        ], 201);
    }

    public function show(Request $request, Student $student)
    {
        $this->ensureCompany($request, $student);

        return response()->json(['student' => $this->loadStudentRelations($student)]);
    }

    public function update(StudentRequest $request, Student $student)
    {
        $this->ensureCompany($request, $student);
        $companyId = (int) $request->header('company');
        $validated = $request->validated();
        $hasFamilyPayload = array_key_exists('family_members', $validated);
        $familyMembers = $validated['family_members'] ?? [];
        unset($validated['family_members']);

        if ($request->header('school-level')) {
            $validated['school_level_id'] = $request->header('school-level');
        }

        DB::transaction(function () use ($student, $validated, $familyMembers, $hasFamilyPayload, $companyId) {
            $student->update($validated);
            if ($hasFamilyPayload) {
                $this->syncFamilyMembers($student, $familyMembers, $companyId);
            }
        });

        return response()->json([
            'student' => $this->loadStudentRelations($student),
            'success' => true,
        ]);
    }

    public function destroy(Request $request, Student $student)
    {
        $this->ensureCompany($request, $student);
        $student->delete();

        return response()->json(['success' => true]);
    }

    private function syncFamilyMembers(Student $student, array $items, $companyId)
    {
        $sync = [];
        $legacyGuardianId = null;

        foreach ($items as $item) {
            $familyMember = $this->resolveFamilyMember($item, $companyId);
            if (isset($sync[$familyMember->id])) {
                continue;
            }

            $isResponsible = ! empty($item['is_responsible']);
            $isFinancial = ! empty($item['is_financial_responsible']);
            $isPrimary = ! empty($item['is_primary_contact']);

            $sync[$familyMember->id] = [
                'company_id' => $companyId,
                'relationship' => $item['relationship'] ?? null,
                'is_responsible' => $isResponsible,
                'is_financial_responsible' => $isFinancial,
                'is_primary_contact' => $isPrimary,
            ];

            if (! $legacyGuardianId && $familyMember->user_id && ($isFinancial || $isResponsible || $isPrimary)) {
                $legacyGuardianId = $familyMember->user_id;
            }
        }

        $student->familyMembers()->sync($sync);

        // Mantiene el campo viejo solo cuando el nuevo responsable esta
        // efectivamente vinculado a un usuario/cliente. Nunca deja apuntando
        // a una persona que ya fue quitada del grupo familiar.
        $student->guardian_id = $legacyGuardianId;
        $student->save();
    }

    private function resolveFamilyMember(array $item, $companyId)
    {
        $dni = $this->normalizeDni($item['dni'] ?? null);

        // El DNI institucional manda incluso si la interfaz traia un ID viejo:
        // de esa forma dos hermanos terminan vinculados a la misma persona.
        if ($dni !== '') {
            $byDni = FamilyMember::where('company_id', $companyId)
                ->where('dni', $dni)
                ->first();
            if ($byDni) {
                $this->updateFamilyMember($byDni, $item, $dni);

                return $byDni;
            }
        }

        if (! empty($item['id'])) {
            $familyMember = FamilyMember::where('company_id', $companyId)->findOrFail($item['id']);
            $this->updateFamilyMember($familyMember, $item, $dni ?: null);

            return $familyMember;
        }

        if (! empty($item['user_id'])) {
            $familyMember = FamilyMember::where('company_id', $companyId)
                ->where('user_id', $item['user_id'])
                ->first();
            if ($familyMember) {
                $this->updateFamilyMember($familyMember, $item, $dni ?: null);

                return $familyMember;
            }
        }

        return FamilyMember::create([
            'company_id' => $companyId,
            'user_id' => $item['user_id'] ?? null,
            'name' => trim($item['name']),
            'dni' => $dni ?: null,
            'email' => $item['email'] ?? null,
            'phone' => $item['phone'] ?? null,
        ]);
    }

    private function updateFamilyMember(FamilyMember $familyMember, array $item, $normalizedDni = null)
    {
        $data = [
            'name' => trim($item['name']),
            'email' => $item['email'] ?? null,
            'phone' => $item['phone'] ?? null,
        ];

        if (array_key_exists('user_id', $item) && ! $familyMember->user_id) {
            $data['user_id'] = $item['user_id'];
        }
        if (array_key_exists('dni', $item)) {
            $data['dni'] = $normalizedDni !== null
                ? $normalizedDni
                : ($this->normalizeDni($item['dni']) ?: null);
        }

        $familyMember->update($data);
    }

    private function loadStudentRelations(Student $student)
    {
        $student->load(['guardian:id,name,email,phone', 'familyMembers']);

        if ($student->familyMembers->isEmpty() && $student->guardian) {
            $student->setAttribute('legacy_family_member', [
                'user_id' => $student->guardian->id,
                'name' => $student->guardian->name,
                'email' => $student->guardian->email,
                'phone' => $student->guardian->phone,
                'relationship' => 'Responsable',
                'is_responsible' => true,
                'is_financial_responsible' => true,
                'is_primary_contact' => true,
            ]);
        }

        return $student;
    }

    private function normalizeDni($dni)
    {
        if ($dni === null) {
            return '';
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $dni)));
    }

    private function ensureCompany(Request $request, Student $student)
    {
        abort_unless((int) $student->company_id === (int) $request->header('company'), 404);
    }
}
