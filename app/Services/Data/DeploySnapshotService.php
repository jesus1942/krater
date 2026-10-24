<?php

namespace Crater\Services\Data;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Crater\Models\AuditLog;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;

class DeploySnapshotService
{
    const TABLES = ['students', 'enrollments', 'invoices', 'estimates', 'payments', 'items', 'expenses'];

    /** Guarda solo IDs tecnicos por grupo; no duplica datos personales. */
    public function memberships(): array
    {
        $groups = [];
        foreach (array_merge(self::TABLES, ['staff_members']) as $table) {
            foreach (DB::table($table)->orderBy('id')->get() as $row) {
                $levels = $table === 'staff_members'
                    ? DB::table('staff_assignments')->where('company_id', $row->company_id)
                        ->where('staff_member_id', $row->id)->pluck('school_level_id')->unique()->all()
                    : [$row->school_level_id];
                if (! $levels) { $levels = [null]; }
                foreach (array_merge(['all'], array_map(function ($level) { return $level === null ? 'null' : (string) $level; }, $levels)) as $level) {
                    $groups[$table.':'.$row->company_id.':'.$level][] = (int) $row->id;
                }
                $groups[$table.':all:all'][] = (int) $row->id;
            }
        }
        ksort($groups);
        return $groups;
    }

    /** Explica solo la identidad perdida, su empresa y una secuencia auditada. */
    protected function auditEvidence($previous, array $memberships, int $cursor): array
    {
        if (! $previous) { return [[], []]; }
        $query = DB::table('audit_logs')->where('id', '<=', $cursor)->orderBy('id');
        if ($previous->audit_cursor !== null) {
            $query->where('id', '>', $previous->audit_cursor);
        } else {
            // Compatibilidad con fotos anteriores a esta migracion.
            $query->where('created_at', '>', $previous->created_at);
        }
        $models = ['Student' => 'students', 'Enrollment' => 'enrollments', 'Invoice' => 'invoices',
            'Estimate' => 'estimates', 'Payment' => 'payments', 'Item' => 'items', 'Expense' => 'expenses',
            'StaffMember' => 'staff_members'];
        $transitions = [];
        $events = [];
        foreach ($query->get() as $event) {
            if ($event->action === 'role_revoked') {
                // Revocar acceso no autoriza a borrar alumnos, cobros o personal.
                $events[] = ['audit_id' => $event->id, 'action' => $event->action, 'company_id' => $event->company_id];
                continue;
            }
            $model = class_basename($event->auditable_type ?? '');
            $table = $models[$model] ?? null;
            if ((! $table && $model !== 'StaffAssignment') || $event->auditable_type !== 'Crater\\Models\\'.$model || ! $event->company_id || ! $event->auditable_id) { continue; }
            $old = json_decode($event->old_values ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?: [];
            $new = json_decode($event->new_values ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?: [];
            if ($model === 'StaffAssignment') {
                $assignment = DB::table('staff_assignments')->where('id', $event->auditable_id)->where('company_id', $event->company_id)->first();
                $memberId = $old['staff_member_id'] ?? $new['staff_member_id'] ?? ($assignment->staff_member_id ?? null);
                if (! $memberId || ! in_array($event->action, ['staffassignment.created', 'staffassignment.updated', 'staffassignment.deleted'], true)) { continue; }
                if ($event->action === 'staffassignment.created') { $old['school_level_id'] = null; }
                if ($event->action === 'staffassignment.deleted') { $new['school_level_id'] = null; }
                if (! array_key_exists('school_level_id', $old) || ! array_key_exists('school_level_id', $new)) { continue; }
                $transitions['staff_members:'.$event->company_id.':'.$memberId][] = [
                    'from' => $old['school_level_id'] === null ? 'null' : (string) $old['school_level_id'],
                    'to' => $new['school_level_id'] === null ? 'null' : (string) $new['school_level_id'],
                    'id' => (int) $event->id, 'action' => $event->action,
                ];
                continue;
            }
            $deleted = $event->action === strtolower($model).'.deleted' || $event->action === 'baja';
            $moved = in_array($event->action, ['level_reassigned', 'student_relocated'], true)
                || (in_array($model, ['Student', 'Enrollment'], true) && $event->action === strtolower($model).'.updated');
            if (! $deleted && ! ($moved && array_key_exists('school_level_id', $old) && array_key_exists('school_level_id', $new))) { continue; }
            // Nunca permitir que un evento de otra empresa justifique esta baja.
            if (isset($old['company_id']) && (int) $old['company_id'] !== (int) $event->company_id) { continue; }
            if (isset($new['company_id']) && (int) $new['company_id'] !== (int) $event->company_id) { continue; }
            $from = array_key_exists('school_level_id', $old) ? $old['school_level_id'] : $event->school_level_id;
            $transitions[$table.':'.$event->company_id.':'.$event->auditable_id][] = [
                'from' => $from === null ? 'null' : (string) $from,
                'to' => $deleted ? 'deleted' : ($new['school_level_id'] === null ? 'null' : (string) $new['school_level_id']),
                'id' => (int) $event->id, 'action' => $event->action,
            ];
        }
        $beforeGroups = $previous->memberships !== null ? json_decode($previous->memberships, true, 512, JSON_THROW_ON_ERROR) : null;
        $explained = [];
        foreach (json_decode($previous->counts, true, 512, JSON_THROW_ON_ERROR) as $key => $count) {
            [$table, $company, $level] = explode(':', $key);
            $candidates = $beforeGroups !== null ? ($beforeGroups[$key] ?? []) : [];
            if ($beforeGroups === null) {
                foreach ($transitions as $object => $steps) {
                    [$candidateTable, $candidateCompany, $id] = explode(':', $object);
                    if ($candidateTable === $table && ($company === 'all' || $company === $candidateCompany)) { $candidates[] = (int) $id; }
                }
            }
            foreach (array_diff($candidates, $memberships[$key] ?? []) as $id) {
                foreach ($transitions as $object => $steps) {
                    [$candidateTable, $candidateCompany, $candidateId] = explode(':', $object);
                    if ($candidateTable !== $table || (int) $candidateId !== $id || ($company !== 'all' && $company !== $candidateCompany)) { continue; }
                    $state = $level === 'all' ? $steps[0]['from'] : $level;
                    $used = [];
                    foreach ($steps as $step) {
                        if ($step['from'] === $state) { $state = $step['to']; $used[] = $step['id']; }
                    }
                    $matches = $state === 'deleted'
                        ? ! in_array($id, $memberships[$table.':all:all'] ?? [], true)
                        : ($level !== 'all' && $state !== $level && in_array($id, $memberships[$table.':'.$candidateCompany.':'.$state] ?? [], true));
                    if ($used && $matches) {
                        $explained[$key][$id] = ['record_id' => $id, 'audit_ids' => $used];
                    }
                }
            }
        }
        return [$explained, $events];
    }

    public function capture(): array
    {
        return DB::transaction(function () {
            $counts = [];
            foreach (array_merge(self::TABLES, ['staff_members']) as $table) {
                // Una tabla ausente es un error, nunca un cero silencioso.
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException('Falta la tabla requerida '.$table.'.');
                }
                $counts[$table.':all:all'] = DB::table($table)->count();
                foreach (DB::table($table)->select('company_id')->selectRaw('COUNT(*) AS n')->groupBy('company_id')->get() as $row) {
                    $counts[$table.':'.$row->company_id.':all'] = (int) $row->n;
                }
                if ($table === 'staff_members') {
                    $query = DB::table('staff_members as m')->leftJoin('staff_assignments as a', function ($join) {
                        $join->on('a.staff_member_id', '=', 'm.id')->on('a.company_id', '=', 'm.company_id');
                    })->select('m.company_id', 'a.school_level_id')->selectRaw('COUNT(DISTINCT m.id) AS n')
                        ->groupBy('m.company_id', 'a.school_level_id');
                } else {
                    $query = DB::table($table)->select('company_id', 'school_level_id')->selectRaw('COUNT(*) AS n')
                        ->groupBy('company_id', 'school_level_id');
                }
                foreach ($query->get() as $row) {
                    $counts[$table.':'.$row->company_id.':'.($row->school_level_id === null ? 'null' : $row->school_level_id)] = (int) $row->n;
                }
                foreach (DB::table('companies')->pluck('id') as $companyId) {
                    $counts[$table.':'.$companyId.':all'] = $counts[$table.':'.$companyId.':all'] ?? 0;
                    $counts[$table.':'.$companyId.':null'] = $counts[$table.':'.$companyId.':null'] ?? 0;
                }
                foreach (DB::table('school_levels')->get(['id', 'company_id']) as $level) {
                    $key = $table.':'.$level->company_id.':'.$level->id;
                    $counts[$key] = $counts[$key] ?? 0;
                }
            }
            ksort($counts);

            return $counts;
        });
    }

    public function check(): array
    {
        return DB::transaction(function () {
            $previous = DB::table('deploy_snapshots')->where('environment', app()->environment())
                ->where('passed', true)->orderByDesc('id')->lockForUpdate()->first();
            $counts = $this->capture();
            $memberships = $this->memberships();
            $cursor = (int) DB::table('audit_logs')->max('id');
            [$auditEvidence, $auditEvents] = $this->auditEvidence($previous, $memberships, $cursor);
            $migrations = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
            $previousMigrations = $previous ? json_decode($previous->applied_migrations, true) : [];
            $allowances = [];
            $declarations = [];
            foreach (config('deploy-data-changes', []) as $declaration) {
                $migration = $declaration['migration'] ?? '';
                if (! in_array($migration, $migrations, true) || in_array($migration, $previousMigrations, true)) {
                    continue;
                }
                if (! preg_match('/^[a-zA-Z0-9_]+$/', $migration) || ! is_file(database_path('migrations/'.$migration.'.php'))
                    || empty(trim($declaration['reason'] ?? '')) || empty($declaration['allowances'])) {
                    throw new RuntimeException('Declaracion de migracion de datos invalida.');
                }
                foreach ($declaration['allowances'] as $key => $maximum) {
                    if (! is_int($maximum) || $maximum < 1 || ! preg_match('/^[a-z_]+:(all|[0-9]+):(all|null|[0-9]+)$/', $key)) {
                        throw new RuntimeException('Limite de migracion de datos invalido.');
                    }
                    $allowances[$key] = ($allowances[$key] ?? 0) + $maximum;
                }
                $declarations[] = $declaration;
            }
            $losses = [];
            $explained = [];
            foreach ($previous ? json_decode($previous->counts, true) : [] as $key => $before) {
                $after = $counts[$key] ?? 0;
                if ($after < $before) {
                    $audited = count($auditEvidence[$key] ?? []);
                    if ($audited) {
                        $explained[$key] = ['before' => $before, 'after' => $after, 'audited' => $audited,
                            'evidence' => array_values($auditEvidence[$key])];
                    }
                    if ($before - $after > $audited + ($allowances[$key] ?? 0)) {
                        $losses[$key] = ['before' => $before, 'after' => $after,
                            'unexplained' => $before - $after - $audited - ($allowances[$key] ?? 0)];
                    }
                }
            }
            $id = DB::table('deploy_snapshots')->insertGetId([
                'environment' => app()->environment(), 'deployment_id' => config('ena-operations.deployment_id'),
                'revision' => config('ena-operations.revision'), 'previous_id' => $previous->id ?? null,
                'passed' => empty($losses), 'counts' => json_encode($counts), 'losses' => json_encode($losses),
                'applied_migrations' => json_encode($migrations), 'declarations' => json_encode($declarations), 'created_at' => now(),
                'audit_cursor' => $cursor, 'memberships' => json_encode($memberships), 'explained_losses' => json_encode($explained),
            ]);

            return ['snapshot_id' => $id, 'previous_id' => $previous->id ?? null, 'passed' => empty($losses),
                'baseline' => ! $previous, 'counts' => $counts, 'losses' => $losses, 'declarations' => $declarations,
                'explained_losses' => $explained, 'audit_events' => $auditEvents, 'audit_cursor' => $cursor];
        });
    }

    /** Acepta una foto vigente, sin mutar la fallida; auditoria y nueva base son atomicas. */
    public function accept(int $snapshotId, User $actor, string $reason): array
    {
        return DB::transaction(function () use ($snapshotId, $actor, $reason) {
            $actor = User::findOrFail($actor->id);
            if (! app(AccessManager::class)->isTotalAdmin($actor)) {
                throw new RuntimeException('Solo administracion total puede aceptar una foto.');
            }
            $reason = trim($reason);
            if ($reason === '' || mb_strlen($reason) > 2000) { throw new RuntimeException('Se requiere un motivo de hasta 2000 caracteres.'); }
            $snapshot = DB::table('deploy_snapshots')->where('environment', app()->environment())->orderByDesc('id')->lockForUpdate()->first();
            if (! $snapshot || (int) $snapshot->id !== $snapshotId || $snapshot->passed) {
                throw new RuntimeException('Solo se acepta la ultima foto fallida de este entorno.');
            }
            $counts = $this->capture();
            $memberships = $this->memberships();
            if ($counts !== json_decode($snapshot->counts, true, 512, JSON_THROW_ON_ERROR)
                || $memberships !== json_decode($snapshot->memberships, true, 512, JSON_THROW_ON_ERROR)) {
                throw new RuntimeException('Los datos cambiaron: ejecutar ena:smoke y revisar la nueva foto.');
            }
            foreach (json_decode($snapshot->losses, true, 512, JSON_THROW_ON_ERROR) as $key => $loss) {
                $company = explode(':', $key)[1];
                if ($company !== 'all' && (int) $company !== (int) $actor->company_id) {
                    throw new RuntimeException('No puede aceptar perdidas de otra institucion.');
                }
            }
            $id = DB::table('deploy_snapshots')->insertGetId([
                'environment' => $snapshot->environment, 'deployment_id' => config('ena-operations.deployment_id'),
                'revision' => config('ena-operations.revision'), 'previous_id' => $snapshot->id, 'passed' => true,
                'counts' => $snapshot->counts, 'losses' => '{}', 'applied_migrations' => $snapshot->applied_migrations,
                'declarations' => $snapshot->declarations, 'memberships' => $snapshot->memberships,
                'audit_cursor' => (int) DB::table('audit_logs')->max('id'), 'explained_losses' => $snapshot->explained_losses,
                'accepted_by' => $actor->id, 'acceptance_reason' => $reason, 'created_at' => now(),
            ]);
            AuditLog::create(['company_id' => $actor->company_id, 'user_id' => $actor->id, 'action' => 'deploy_snapshot_accepted',
                'auditable_type' => 'deploy_snapshots', 'auditable_id' => $id, 'old_values' => ['snapshot_id' => $snapshotId, 'losses' => json_decode($snapshot->losses, true)],
                'new_values' => ['baseline_id' => $id, 'reason' => $reason, 'environment' => app()->environment()],
                'severity' => AuditLog::SEVERITY_CRITICAL, 'created_at' => now()]);
            return ['passed' => true, 'snapshot_id' => $id, 'accepted_snapshot_id' => $snapshotId, 'accepted_by' => $actor->id];
        });
    }
}
