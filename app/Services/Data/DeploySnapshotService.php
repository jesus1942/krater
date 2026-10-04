<?php

namespace Crater\Services\Data;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DeploySnapshotService
{
    const TABLES = ['students', 'enrollments', 'invoices', 'estimates', 'payments', 'items', 'expenses'];

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
            foreach ($previous ? json_decode($previous->counts, true) : [] as $key => $before) {
                $after = $counts[$key] ?? 0;
                if ($after < $before && $before - $after > ($allowances[$key] ?? 0)) {
                    $losses[$key] = ['before' => $before, 'after' => $after];
                }
            }
            $id = DB::table('deploy_snapshots')->insertGetId([
                'environment' => app()->environment(), 'deployment_id' => config('ena-operations.deployment_id'),
                'revision' => config('ena-operations.revision'), 'previous_id' => $previous->id ?? null,
                'passed' => empty($losses), 'counts' => json_encode($counts), 'losses' => json_encode($losses),
                'applied_migrations' => json_encode($migrations), 'declarations' => json_encode($declarations), 'created_at' => now(),
            ]);

            return ['snapshot_id' => $id, 'previous_id' => $previous->id ?? null, 'passed' => empty($losses),
                'baseline' => ! $previous, 'counts' => $counts, 'losses' => $losses, 'declarations' => $declarations];
        });
    }
}
