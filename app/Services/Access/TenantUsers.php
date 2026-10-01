<?php

namespace Crater\Services\Access;

use Crater\Models\User;
use Crater\Support\TenantContext;

/** Consultas compartidas por usuarios, clientes y busqueda; nunca usan headers. */
class TenantUsers
{
    public function staff(User $actor)
    {
        $query = User::where('users.company_id', TenantContext::companyId())
            ->where(function ($accounts) {
                $accounts->where('users.role', '<>', 'customer')->orWhereExists(function ($roles) {
                    $roles->selectRaw('1')->from('role_user')->whereColumn('role_user.user_id', 'users.id')
                        ->where('role_user.company_id', TenantContext::companyId());
                });
            });
        $access = app(AccessManager::class);
        if ($access->isTotalAdmin($actor)) {
            return $query;
        }
        $levelIds = $access->levelIds($actor);
        $levelId = TenantContext::schoolLevelId();
        if ($levelId !== null) {
            $levelIds = array_values(array_intersect($levelIds, [$levelId]));
        }
        $today = now()->toDateString();

        return $query->whereExists(function ($q) use ($levelIds, $today) {
            $q->selectRaw('1')->from('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->whereColumn('role_user.user_id', 'users.id')
                ->where('role_user.company_id', TenantContext::companyId())
                ->where('roles.company_id', TenantContext::companyId())
                ->whereIn('role_user.school_level_id', $levelIds)
                ->where(fn ($date) => $date->whereNull('role_user.starts_on')->orWhere('role_user.starts_on', '<=', $today))
                ->where(fn ($date) => $date->whereNull('role_user.ends_on')->orWhere('role_user.ends_on', '>=', $today));
        });
    }

    public function customers()
    {
        $query = User::customer()->where('users.company_id', TenantContext::companyId());
        $levelId = TenantContext::schoolLevelId();
        if ($levelId === null) {
            return $query;
        }

        return $query->where(function ($family) use ($levelId) {
            $family->whereHas('students', function ($students) use ($levelId) {
                $students->where('students.company_id', TenantContext::companyId())
                    ->where('students.school_level_id', $levelId);
            })->orWhereExists(function ($canonical) use ($levelId) {
                $canonical->selectRaw('1')->from('family_members')
                    ->join('student_family_members', 'student_family_members.family_member_id', '=', 'family_members.id')
                    ->join('students', 'students.id', '=', 'student_family_members.student_id')
                    ->whereColumn('family_members.user_id', 'users.id')
                    ->where('student_family_members.company_id', TenantContext::companyId())
                    ->where('family_members.company_id', TenantContext::companyId())
                    ->where('students.company_id', TenantContext::companyId())
                    ->where('students.school_level_id', $levelId);
            });
        });
    }

    public function canViewStaff(User $actor, User $target): bool
    {
        return $this->staff($actor)->whereKey($target->id)->exists();
    }

    public function canViewCustomer(User $target): bool
    {
        return $this->customers()->whereKey($target->id)->exists();
    }
}
