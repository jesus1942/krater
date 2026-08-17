<?php

namespace Crater\Http\Controllers\V1\Staff;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Models\SchoolLevel;
use Crater\Models\StaffAssignment;
use Crater\Models\StaffMember;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffMembersController extends Controller
{
    protected AccessManager $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $global = $this->access->allows($user, Permission::USER_VIEW, null);
        $levelId = TenantContext::schoolLevelId();

        if (! $global && ! $this->access->allows($user, Permission::USER_VIEW, $levelId)) {
            abort(403);
        }

        $query = StaffMember::query()
            ->where('company_id', TenantContext::companyId())
            ->with(['assignments' => function ($q) use ($global, $levelId) {
                $q->with('schoolLevel:id,name,code')->orderByDesc('active')->orderByDesc('start_date');
                if (! $global) {
                    $q->where('school_level_id', $levelId);
                }
            }]);

        if (! $global) {
            $query->whereHas('assignments', fn ($q) => $q->where('school_level_id', $levelId));
        }

        $members = $query->orderBy('last_name')->orderBy('first_name')->get();

        $levels = SchoolLevel::where('company_id', TenantContext::companyId())
            ->where('enabled', true)
            ->when(! $global, fn ($q) => $q->whereKey($levelId))
            ->orderBy('id')
            ->get(['id', 'name', 'code']);

        return response()->json(['data' => $members, 'levels' => $levels, 'global' => $global]);
    }

    public function store(Request $request)
    {
        $this->assertCanManage($request);
        $data = $this->validateMember($request);
        $assignment = $this->validateAssignment($request, true);
        $this->assertLevelAllowed($request, $assignment['school_level_id'] ?? null);
        $this->assertUserCompany($data['user_id'] ?? null);

        $member = DB::transaction(function () use ($data, $assignment) {
            $data['company_id'] = TenantContext::companyId();
            $data['document_number_normalized'] = $this->normalizeDocument($data['document_number'] ?? null);
            $member = StaffMember::create($data);
            $assignment['company_id'] = TenantContext::companyId();
            $member->assignments()->create($assignment);
            return $member;
        });

        return response()->json(['data' => $member->fresh('assignments.schoolLevel')], 201);
    }

    public function update(Request $request, StaffMember $staffMember)
    {
        $this->assertCompany($staffMember);
        $this->assertCanManage($request);
        $data = $this->validateMember($request, $staffMember->id);
        $this->assertUserCompany($data['user_id'] ?? null);
        $data['document_number_normalized'] = $this->normalizeDocument($data['document_number'] ?? null);
        $staffMember->update($data);
        return response()->json(['data' => $staffMember->fresh('assignments.schoolLevel')]);
    }

    public function storeAssignment(Request $request, StaffMember $staffMember)
    {
        $this->assertCompany($staffMember);
        $this->assertCanManage($request);
        $data = $this->validateAssignment($request, true);
        $this->assertLevelAllowed($request, $data['school_level_id'] ?? null);
        $data['company_id'] = TenantContext::companyId();
        $assignment = $staffMember->assignments()->create($data);
        return response()->json(['data' => $assignment->load('schoolLevel')], 201);
    }

    public function updateAssignment(Request $request, StaffMember $staffMember, StaffAssignment $staffAssignment)
    {
        $this->assertCompany($staffMember);
        abort_unless((int) $staffAssignment->staff_member_id === (int) $staffMember->id, 404);
        abort_unless((int) $staffAssignment->company_id === (int) TenantContext::companyId(), 404);
        $this->assertCanManage($request);
        $data = $this->validateAssignment($request, false);
        $this->assertLevelAllowed($request, $data['school_level_id'] ?? $staffAssignment->school_level_id);
        $staffAssignment->update($data);
        return response()->json(['data' => $staffAssignment->fresh('schoolLevel')]);
    }

    protected function assertCanManage(Request $request): void
    {
        $user = $request->user();
        if ($this->access->allows($user, Permission::USER_MANAGE, null)) {
            return;
        }
        if (! $this->access->allows($user, Permission::USER_MANAGE, TenantContext::schoolLevelId())) {
            abort(403);
        }
    }

    protected function assertLevelAllowed(Request $request, ?int $levelId): void
    {
        if ($levelId === null) {
            if (! $this->access->allows($request->user(), Permission::USER_MANAGE, null)) {
                throw ValidationException::withMessages(['school_level_id' => ['Solo la administración global puede crear cargos institucionales sin nivel.']]);
            }
            return;
        }

        $level = SchoolLevel::where('company_id', TenantContext::companyId())->findOrFail($levelId);
        if ($this->access->allows($request->user(), Permission::USER_MANAGE, null)) {
            return;
        }
        if ((int) $level->id !== (int) TenantContext::schoolLevelId()) {
            abort(403);
        }
    }

    protected function assertCompany(StaffMember $staffMember): void
    {
        abort_unless((int) $staffMember->company_id === (int) TenantContext::companyId(), 404);
    }

    protected function assertUserCompany(?int $userId): void
    {
        if ($userId === null) return;
        if (! User::where('company_id', TenantContext::companyId())->whereKey($userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => ['La cuenta de acceso no pertenece a esta institución.']]);
        }
    }

    protected function validateMember(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer'],
            'document_type' => ['nullable', 'string', 'max:30'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:60'],
            'staff_category' => ['required', Rule::in(['teaching', 'administrative', 'maintenance', 'cleaning', 'management', 'support', 'other'])],
            'employment_status' => ['required', Rule::in(['active', 'leave', 'inactive', 'terminated'])],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    protected function validateAssignment(Request $request, bool $required): array
    {
        $prefix = $required ? 'required' : 'sometimes';
        return $request->validate([
            'school_level_id' => ['nullable', 'integer'],
            'position_code' => ['nullable', 'string', 'max:60'],
            'position_title' => [$prefix, 'string', 'max:150'],
            'function_category' => [$prefix, Rule::in(['teaching', 'administrative', 'maintenance', 'cleaning', 'management', 'support', 'other'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'active' => ['sometimes', 'boolean'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    protected function normalizeDocument(?string $document): ?string
    {
        if ($document === null || trim($document) === '') return null;
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $document));
    }
}
