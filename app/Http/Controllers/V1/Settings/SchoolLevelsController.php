<?php

namespace Crater\Http\Controllers\V1\Settings;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Models\SchoolLevel;
use Crater\Services\Access\AccessManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolLevelsController extends Controller
{
    protected $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    protected function authorizeTotalAdmin(Request $request): void
    {
        abort_unless(
            $this->access->allows($request->user(), Permission::SCHOOL_LEVEL_MANAGE),
            403
        );
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($request->header('company') ?: $user->company_id);

        abort_unless(
            $this->access->isTotalAdmin($user) || $companyId === (int) $user->company_id,
            403
        );

        $query = SchoolLevel::where('company_id', $companyId)
            ->where('enabled', true)
            ->orderByRaw("CASE code WHEN 'primary' THEN 1 WHEN 'secondary' THEN 2 WHEN 'tertiary' THEN 3 ELSE 4 END");

        if (! $this->access->isTotalAdmin($user) && ! $this->hasInstitutionWideRole($user->id, $companyId)) {
            $levelIds = DB::table('school_level_user')
                ->where('user_id', $user->id)
                ->pluck('school_level_id')
                ->merge(
                    DB::table('role_user')
                        ->where('user_id', $user->id)
                        ->where('company_id', $companyId)
                        ->whereNotNull('school_level_id')
                        ->where(function ($q) {
                            $q->whereNull('starts_on')->orWhere('starts_on', '<=', now()->toDateString());
                        })
                        ->where(function ($q) {
                            $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString());
                        })
                        ->pluck('school_level_id')
                )
                ->unique()
                ->values();

            $query->whereIn('id', $levelIds);
        }

        return response()->json([
            'levels' => $query->get(),
            'can_view_whole_institution' => $this->access->isTotalAdmin($user),
        ]);
    }

    protected function hasInstitutionWideRole($userId, $companyId): bool
    {
        $today = now()->toDateString();

        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->where('role_user.company_id', $companyId)
            ->where('roles.company_id', $companyId)
            ->where('roles.scope_type', 'global')
            ->whereNull('role_user.school_level_id')
            ->where(function ($query) use ($today) {
                $query->whereNull('role_user.starts_on')->orWhere('role_user.starts_on', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('role_user.ends_on')->orWhere('role_user.ends_on', '>=', $today);
            })
            ->exists();
    }

    public function update(Request $request, SchoolLevel $schoolLevel)
    {
        $this->authorizeTotalAdmin($request);

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
