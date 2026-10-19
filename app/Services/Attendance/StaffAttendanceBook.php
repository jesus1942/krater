<?php

namespace Crater\Services\Attendance;

use Crater\Enums\Permission;
use Crater\Models\StaffAttendance;
use Crater\Models\StaffLeave;
use Crater\Models\StaffMember;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Services\Audit\Auditor;
use Crater\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asistencia diaria y licencias del plantel. Una persona tiene una sola fila
 * por dia aunque trabaje en varios niveles: si falta, falta en todos. La
 * direccion o secretaria de un nivel registra a quien tiene cargo o docencia
 * vigente en su nivel; la administracion total, a cualquiera.
 */
class StaffAttendanceBook
{
    protected AccessManager $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    /** Personal activo del nivel del encabezado (toda la institucion si no hay nivel). */
    public function membersQuery(?int $level)
    {
        $query = StaffMember::query()->where('company_id', TenantContext::companyId())->where('employment_status', 'active');
        if ($level) {
            $query->where(fn ($q) => $q->whereHas('assignments', fn ($a) => $a->where('active', true)->where('school_level_id', $level))
                ->orWhereHas('teachingAssignments', fn ($t) => $t->whereNull('revoked_at')->where('school_level_id', $level)));
        }

        return $query->orderBy('last_name')->orderBy('first_name');
    }

    public function day(User $user, string $date): array
    {
        $level = TenantContext::schoolLevelId();
        $this->authorize($user, Permission::HR_ATTENDANCE_VIEW, $level);
        $members = $this->membersQuery($level)->with(['assignments' => fn ($q) => $q->where('active', true)])->get();
        $records = StaffAttendance::where('company_id', TenantContext::companyId())->whereDate('date', $date)
            ->whereIn('staff_member_id', $members->modelKeys())->get()->keyBy('staff_member_id');
        $leaves = StaffLeave::active()->where('company_id', TenantContext::companyId())->covering($date)
            ->whereIn('staff_member_id', $members->modelKeys())->get()->keyBy('staff_member_id');

        return [
            'date' => $date,
            'can_record' => $this->access->allows($user, Permission::HR_ATTENDANCE_RECORD, $level),
            'leave_types' => StaffLeave::TYPES,
            'members' => $members->map(function ($m) use ($records, $leaves) {
                $leave = $leaves[$m->id] ?? null;
                $record = $records[$m->id] ?? null;

                return ['staff_member_id' => $m->id, 'name' => trim($m->last_name.', '.$m->first_name, ', '),
                    'roles' => $m->assignments->pluck('position_title')->unique()->values(),
                    'status' => $leave ? 'leave' : optional($record)->status, 'notes' => optional($record)->notes,
                    'leave' => $leave ? ['id' => $leave->id, 'type' => $leave->type, 'type_label' => StaffLeave::TYPES[$leave->type] ?? $leave->type,
                        'starts_on' => $leave->starts_on->toDateString(), 'ends_on' => $leave->ends_on->toDateString()] : null];
            })->values(),
        ];
    }

    public function save(User $user, string $date, array $rows): array
    {
        $level = TenantContext::schoolLevelId();
        $this->authorize($user, Permission::HR_ATTENDANCE_RECORD, $level);
        if ($date > now()->toDateString()) {
            throw ValidationException::withMessages(['date' => 'No se puede registrar asistencia de un día futuro.']);
        }
        $allowed = $this->membersQuery($level)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $onLeave = StaffLeave::active()->where('company_id', TenantContext::companyId())->covering($date)->pluck('staff_member_id')
            ->map(fn ($id) => (int) $id)->all();
        DB::transaction(function () use ($user, $date, $rows, $allowed, $onLeave) {
            $changes = 0;
            foreach ($rows as $row) {
                $member = (int) $row['staff_member_id'];
                if (! in_array($member, $allowed, true)) {
                    abort(403, 'Hay personas que no trabajan en este nivel.');
                }
                if (in_array($member, $onLeave, true)) {
                    continue; // La licencia manda: se cancela desde Licencias.
                }
                $record = StaffAttendance::where('staff_member_id', $member)->whereDate('date', $date)->lockForUpdate()->first();
                $values = ['status' => $row['status'], 'notes' => $row['notes'] ?? null, 'recorded_by' => $user->id];
                if ($record) {
                    $record->fill($values);
                    if ($record->isDirty(['status', 'notes'])) {
                        $record->save();
                        $changes++;
                    }
                } else {
                    StaffAttendance::create($values + ['company_id' => TenantContext::companyId(), 'staff_member_id' => $member, 'date' => $date]);
                    $changes++;
                }
            }
            app(Auditor::class)->record('staff_attendance.recorded', $user, ['type' => StaffAttendance::class, 'id' => null],
                [], ['date' => $date, 'rows' => count($rows), 'changes' => $changes]);
        });

        return $this->day($user, $date);
    }

    public function leaves(User $user, string $from, string $to)
    {
        $level = TenantContext::schoolLevelId();
        $this->authorize($user, Permission::HR_ATTENDANCE_VIEW, $level);
        $members = $this->membersQuery($level)->pluck('id');

        return StaffLeave::with('member:id,first_name,last_name')->where('company_id', TenantContext::companyId())
            ->whereIn('staff_member_id', $members)->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)
            ->orderByDesc('starts_on')->get()->map(fn ($l) => $this->presentLeave($l))->values();
    }

    public function createLeave(User $user, array $data): array
    {
        $level = TenantContext::schoolLevelId();
        $this->authorize($user, Permission::HR_ATTENDANCE_RECORD, $level);
        abort_unless($this->membersQuery($level)->whereKey($data['staff_member_id'])->exists(), 403, 'La persona no trabaja en este nivel.');
        if ($data['ends_on'] < $data['starts_on']) {
            throw ValidationException::withMessages(['ends_on' => 'La licencia termina antes de empezar.']);
        }

        return DB::transaction(function () use ($user, $data) {
            StaffMember::whereKey($data['staff_member_id'])->lockForUpdate()->first();
            $overlap = StaffLeave::active()->where('staff_member_id', $data['staff_member_id'])
                ->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => 'Ya tiene una licencia que se superpone con esas fechas.']);
            }
            $leave = StaffLeave::create(['company_id' => TenantContext::companyId(), 'staff_member_id' => $data['staff_member_id'],
                'type' => $data['type'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
                'notes' => $data['notes'] ?? null, 'created_by' => $user->id]);
            app(Auditor::class)->record('staff_leave.created', $user, ['type' => StaffLeave::class, 'id' => $leave->id],
                [], ['staff_member_id' => $leave->staff_member_id, 'type' => $leave->type, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on']]);

            return $this->presentLeave($leave->load('member:id,first_name,last_name'));
        });
    }

    public function cancelLeave(User $user, StaffLeave $leave): array
    {
        $level = TenantContext::schoolLevelId();
        $this->authorize($user, Permission::HR_ATTENDANCE_RECORD, $level);
        abort_unless((int) $leave->company_id === TenantContext::companyId(), 404);
        abort_unless($this->membersQuery($level)->whereKey($leave->staff_member_id)->exists(), 403);
        if (! $leave->cancelled_at) {
            $leave->update(['cancelled_at' => now(), 'cancelled_by' => $user->id]);
            app(Auditor::class)->record('staff_leave.cancelled', $user, ['type' => StaffLeave::class, 'id' => $leave->id], [], []);
        }

        return $this->presentLeave($leave->fresh()->load('member:id,first_name,last_name'));
    }

    protected function presentLeave(StaffLeave $leave): array
    {
        return ['id' => $leave->id, 'staff_member_id' => $leave->staff_member_id,
            'name' => $leave->member ? trim($leave->member->last_name.', '.$leave->member->first_name, ', ') : null,
            'type' => $leave->type, 'type_label' => StaffLeave::TYPES[$leave->type] ?? $leave->type,
            'starts_on' => $leave->starts_on->toDateString(), 'ends_on' => $leave->ends_on->toDateString(),
            'notes' => $leave->notes, 'cancelled' => (bool) $leave->cancelled_at];
    }

    protected function authorize(User $user, string $permission, ?int $level): void
    {
        if ($level === null) {
            abort_unless($this->access->isTotalAdmin($user) || $this->access->allows($user, $permission, null), 403);

            return;
        }
        abort_unless($this->access->allows($user, $permission, $level), 403);
    }
}
