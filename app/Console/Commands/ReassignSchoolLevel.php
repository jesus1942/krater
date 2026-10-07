<?php

namespace Crater\Console\Commands;

use Crater\Services\Data\LevelReconciliationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ReassignSchoolLevel extends Command
{
    protected $signature = 'ena:reubicar-nivel
        {modelo}
        {ids*}
        {--nivel= : ID del nivel destino}
        {--aplicar : Ejecuta los cambios; sin esta opcion solo simula}
        {--company=1 : ID de empresa}
        {--motivo=Reconciliacion manual por consola : Motivo de auditoria}';

    protected $description = 'Simula o aplica la reconciliacion de registros sin nivel';

    protected $service;

    public function __construct(LevelReconciliationService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $companyId = (int) $this->option('company');
        $levelId = (int) $this->option('nivel');

        if ($companyId <= 0 || $levelId <= 0) {
            $this->error('--company y --nivel deben ser IDs numericos validos.');

            return self::FAILURE;
        }

        try {
            if (! $this->option('aplicar')) {
                $preview = $this->service->preview(
                    (string) $this->argument('modelo'),
                    (array) $this->argument('ids'),
                    $levelId,
                    $companyId
                );

                $this->info('SIMULACION - no se escribieron datos.');
                $this->printRows($preview);

                return self::SUCCESS;
            }

            $result = $this->service->apply(
                (string) $this->argument('modelo'),
                (array) $this->argument('ids'),
                $levelId,
                $companyId,
                null,
                (string) $this->option('motivo')
            );

            $this->info('Reconciliacion aplicada.');
            $this->printRows($result);

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function printRows(array $rows): void
    {
        $this->table(
            ['Modelo', 'ID', 'Nivel anterior', 'Nivel destino'],
            array_map(function ($row) {
                return [
                    $row['model'],
                    $row['id'],
                    $row['from_school_level_id'] === null ? 'NULL' : $row['from_school_level_id'],
                    $row['to_school_level_id'],
                ];
            }, $rows)
        );
    }
}
