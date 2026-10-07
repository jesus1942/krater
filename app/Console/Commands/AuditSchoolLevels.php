<?php

namespace Crater\Console\Commands;

use Crater\Models\Company;
use Crater\Services\Data\LevelAuditService;
use Illuminate\Console\Command;

class AuditSchoolLevels extends Command
{
    protected $signature = 'ena:auditar-niveles {--company=} {--json}';

    protected $description = 'Audita asignaciones de school_level_id sin modificar datos';

    protected $service;

    public function __construct(LevelAuditService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $companyOption = $this->option('company');

        if ($companyOption !== null && $companyOption !== '' && ! ctype_digit((string) $companyOption)) {
            $this->error('--company debe ser un ID numerico.');

            return self::FAILURE;
        }

        $companyId = ($companyOption === null || $companyOption === '') ? null : (int) $companyOption;

        if ($companyId && ! Company::query()->where('id', $companyId)->exists()) {
            $this->error('No existe la empresa #'.$companyId.'.');

            return self::FAILURE;
        }

        $report = $this->service->audit($companyId);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Auditoria de niveles - SOLO LECTURA');

        foreach ($report['companies'] as $companyReport) {
            $company = $companyReport['company'];
            $this->newLine();
            $this->line('Empresa #'.$company['id'].' - '.$company['name']);

            $rows = [];
            foreach ($companyReport['models'] as $name => $stats) {
                $rows[] = [
                    $name,
                    $stats['total'],
                    $stats['valid_level'],
                    $stats['null_level'],
                    $stats['missing_level'],
                    $stats['foreign_company_level'],
                ];
            }

            $this->table(
                ['Modelo', 'Total', 'Nivel valido', 'NULL', 'Inexistente', 'Otra empresa'],
                $rows
            );

            foreach ($companyReport['economic_suggestions'] as $name => $suggestions) {
                $this->line('Sugerencias para '.$name.':');
                $suggestionRows = [];

                foreach ($suggestions as $suggestion) {
                    $candidateText = [];
                    foreach ($suggestion['candidates'] as $candidate) {
                        $candidateText[] = '#'.$candidate['school_level_id'].' '.$candidate['name'].' ['.implode(', ', $candidate['sources']).']';
                    }

                    $suggestionRows[] = [
                        $suggestion['id'],
                        $suggestion['current_school_level_id'] === null ? 'NULL' : $suggestion['current_school_level_id'],
                        $suggestion['status'],
                        $suggestion['suggested_school_level_id'] === null ? '-' : $suggestion['suggested_school_level_id'],
                        implode(' | ', $candidateText),
                    ];
                }

                $this->table(['ID', 'Actual', 'Estado', 'Sugerido', 'Evidencia'], $suggestionRows);
            }
        }

        if (! $report['origin_links']['invoice_to_estimate']) {
            $this->warn('Limitacion detectada: invoices no conserva estimate_id; no se puede reconstruir Estimate -> Invoice de forma determinista.');
        }

        return self::SUCCESS;
    }
}
