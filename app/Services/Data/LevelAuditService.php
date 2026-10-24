<?php

namespace Crater\Services\Data;

use Crater\Models\AcademicYear;
use Crater\Models\Company;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\GradeLevel;
use Crater\Models\Invoice;
use Crater\Models\Item;
use Crater\Models\Payment;
use Crater\Models\Student;
use Crater\Models\StudyPlan;
use Crater\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LevelAuditService
{
    const MODELS = [
        'StudyPlan' => StudyPlan::class,
        'CourseSection' => CourseSection::class,
        'GradeLevel' => GradeLevel::class,
        'Estimate' => Estimate::class,
        'AcademicYear' => AcademicYear::class,
        'Student' => Student::class,
        'Division' => Division::class,
        'Item' => Item::class,
        'Enrollment' => Enrollment::class,
        'Payment' => Payment::class,
        'Invoice' => Invoice::class,
        'Expense' => Expense::class,
        'Subject' => Subject::class,
    ];

    const ECONOMIC_MODELS = [
        'Estimate' => Estimate::class,
        'Invoice' => Invoice::class,
        'Payment' => Payment::class,
        'Expense' => Expense::class,
    ];

    protected $levelCache = [];

    public function audit($companyId = null)
    {
        $companies = Company::query()
            ->select(['id', 'name'])
            ->when($companyId, function ($query) use ($companyId) {
                $query->where('id', $companyId);
            })
            ->orderBy('id')
            ->get();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'origin_links' => [
                'payment_to_invoice' => Schema::hasColumn('payments', 'invoice_id'),
                'invoice_to_estimate' => Schema::hasColumn('invoices', 'estimate_id'),
            ],
            'companies' => [],
        ];

        foreach ($companies as $company) {
            $companyReport = [
                'company' => [
                    'id' => (int) $company->id,
                    'name' => $company->name,
                ],
                'models' => [],
                'economic_suggestions' => [],
            ];

            foreach (self::MODELS as $name => $modelClass) {
                $companyReport['models'][$name] = $this->modelStats($modelClass, (int) $company->id);
            }

            foreach (self::ECONOMIC_MODELS as $name => $modelClass) {
                $suggestions = $this->economicSuggestions($name, $modelClass, (int) $company->id);
                if (! empty($suggestions)) {
                    $companyReport['economic_suggestions'][$name] = $suggestions;
                }
            }

            $report['companies'][] = $companyReport;
        }

        return $report;
    }

    protected function modelStats($modelClass, $companyId)
    {
        $model = new $modelClass();
        $table = $model->getTable();

        $row = $modelClass::withoutGlobalScopes()
            ->from($table)
            ->leftJoin('school_levels as audit_levels', 'audit_levels.id', '=', $table.'.school_level_id')
            ->where($table.'.company_id', $companyId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$table.'.school_level_id IS NOT NULL AND audit_levels.id IS NOT NULL AND audit_levels.company_id = '.$table.'.company_id THEN 1 ELSE 0 END), 0) as valid_level')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$table.'.school_level_id IS NULL THEN 1 ELSE 0 END), 0) as null_level')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$table.'.school_level_id IS NOT NULL AND audit_levels.id IS NULL THEN 1 ELSE 0 END), 0) as missing_level')
            ->selectRaw('COALESCE(SUM(CASE WHEN audit_levels.id IS NOT NULL AND audit_levels.company_id <> '.$table.'.company_id THEN 1 ELSE 0 END), 0) as foreign_company_level')
            ->first();

        return [
            'table' => $table,
            'total' => (int) $row->total,
            'valid_level' => (int) $row->valid_level,
            'null_level' => (int) $row->null_level,
            'missing_level' => (int) $row->missing_level,
            'foreign_company_level' => (int) $row->foreign_company_level,
        ];
    }

    protected function economicSuggestions($name, $modelClass, $companyId)
    {
        $model = new $modelClass();
        $table = $model->getTable();

        $records = $modelClass::withoutGlobalScopes()
            ->from($table)
            ->leftJoin('school_levels as audit_levels', 'audit_levels.id', '=', $table.'.school_level_id')
            ->where($table.'.company_id', $companyId)
            ->where(function ($query) use ($table) {
                $query->whereNull($table.'.school_level_id')
                    ->orWhereNull('audit_levels.id')
                    ->orWhereColumn('audit_levels.company_id', '<>', $table.'.company_id');
            })
            ->select($table.'.*')
            ->orderBy($table.'.id')
            ->get();

        $suggestions = [];
        foreach ($records as $record) {
            $candidates = $this->candidatesFor($name, $record, $companyId);
            $candidateIds = array_map('intval', array_keys($candidates));

            $status = 'sin_evidencia';
            $suggested = null;
            if (count($candidateIds) === 1) {
                $status = 'unico';
                $suggested = $candidateIds[0];
            } elseif (count($candidateIds) > 1) {
                $status = 'ambiguo';
            }

            $suggestions[] = [
                'id' => (int) $record->id,
                'reference' => $this->referenceFor($name, $record),
                'current_school_level_id' => $record->school_level_id === null ? null : (int) $record->school_level_id,
                'status' => $status,
                'suggested_school_level_id' => $suggested,
                'candidates' => array_values($candidates),
            ];
        }

        return $suggestions;
    }

    protected function referenceFor($name, $record)
    {
        if ($name === 'Estimate') {
            return [
                'number' => isset($record->estimate_number) ? $record->estimate_number : null,
                'date' => isset($record->estimate_date) ? (string) $record->estimate_date : null,
                'user_id' => isset($record->user_id) && $record->user_id ? (int) $record->user_id : null,
                'total' => isset($record->total) ? (int) $record->total : null,
            ];
        }

        if ($name === 'Invoice') {
            return [
                'number' => isset($record->invoice_number) ? $record->invoice_number : null,
                'date' => isset($record->invoice_date) ? (string) $record->invoice_date : null,
                'user_id' => isset($record->user_id) && $record->user_id ? (int) $record->user_id : null,
                'student_id' => isset($record->student_id) && $record->student_id ? (int) $record->student_id : null,
                'enrollment_id' => isset($record->enrollment_id) && $record->enrollment_id ? (int) $record->enrollment_id : null,
                'total' => isset($record->total) ? (int) $record->total : null,
            ];
        }

        if ($name === 'Payment') {
            return [
                'number' => isset($record->payment_number) ? $record->payment_number : null,
                'date' => isset($record->payment_date) ? (string) $record->payment_date : null,
                'user_id' => isset($record->user_id) && $record->user_id ? (int) $record->user_id : null,
                'student_id' => isset($record->student_id) && $record->student_id ? (int) $record->student_id : null,
                'invoice_id' => isset($record->invoice_id) && $record->invoice_id ? (int) $record->invoice_id : null,
                'amount' => isset($record->amount) ? (int) $record->amount : null,
            ];
        }

        if ($name === 'Expense') {
            return [
                'date' => isset($record->expense_date) ? (string) $record->expense_date : null,
                'user_id' => isset($record->user_id) && $record->user_id ? (int) $record->user_id : null,
                'amount' => isset($record->amount) ? (int) $record->amount : null,
                'expense_category_id' => isset($record->expense_category_id) && $record->expense_category_id
                    ? (int) $record->expense_category_id
                    : null,
            ];
        }

        return [];
    }

    protected function candidatesFor($name, $record, $companyId)
    {
        $candidates = [];

        if (isset($record->enrollment_id) && $record->enrollment_id) {
            $enrollment = DB::table('enrollments')
                ->where('id', $record->enrollment_id)
                ->where('company_id', $companyId)
                ->first();

            if ($enrollment) {
                $this->addCandidate($candidates, $enrollment->school_level_id, 'matricula:'.$enrollment->id, $companyId);
            }
        }

        if (isset($record->student_id) && $record->student_id) {
            $student = DB::table('students')
                ->where('id', $record->student_id)
                ->where('company_id', $companyId)
                ->first();

            if ($student) {
                $this->addCandidate($candidates, $student->school_level_id, 'alumno:'.$student->id, $companyId);
            }
        }

        if ($name === 'Payment' && isset($record->invoice_id) && $record->invoice_id) {
            $invoice = DB::table('invoices')
                ->where('id', $record->invoice_id)
                ->where('company_id', $companyId)
                ->first();

            if ($invoice) {
                $this->addCandidate($candidates, $invoice->school_level_id, 'factura:'.$invoice->id, $companyId);

                if (isset($invoice->enrollment_id) && $invoice->enrollment_id) {
                    $enrollment = DB::table('enrollments')
                        ->where('id', $invoice->enrollment_id)
                        ->where('company_id', $companyId)
                        ->first();

                    if ($enrollment) {
                        $this->addCandidate($candidates, $enrollment->school_level_id, 'factura:'.$invoice->id.'->matricula:'.$enrollment->id, $companyId);
                    }
                }

                if (isset($invoice->student_id) && $invoice->student_id) {
                    $student = DB::table('students')
                        ->where('id', $invoice->student_id)
                        ->where('company_id', $companyId)
                        ->first();

                    if ($student) {
                        $this->addCandidate($candidates, $student->school_level_id, 'factura:'.$invoice->id.'->alumno:'.$student->id, $companyId);
                    }
                }
            }
        }

        if (isset($record->user_id) && $record->user_id) {
            $students = DB::table('students')
                ->select(['id', 'school_level_id'])
                ->where('company_id', $companyId)
                ->where('guardian_id', $record->user_id)
                ->whereNotNull('school_level_id')
                ->orderBy('id')
                ->get();

            foreach ($students as $student) {
                $this->addCandidate(
                    $candidates,
                    $student->school_level_id,
                    'cliente:'.$record->user_id.'->alumno:'.$student->id,
                    $companyId
                );
            }
        }

        if ($name === 'Invoice' && Schema::hasColumn('invoices', 'estimate_id') && isset($record->estimate_id) && $record->estimate_id) {
            $estimate = DB::table('estimates')
                ->where('id', $record->estimate_id)
                ->where('company_id', $companyId)
                ->first();

            if ($estimate) {
                $this->addCandidate($candidates, $estimate->school_level_id, 'presupuesto:'.$estimate->id, $companyId);
            }
        }

        return $candidates;
    }

    protected function addCandidate(array &$candidates, $levelId, $source, $companyId)
    {
        if (! $levelId) {
            return;
        }

        $levels = $this->levelsForCompany($companyId);
        $levelId = (int) $levelId;

        if (! isset($levels[$levelId])) {
            return;
        }

        if (! isset($candidates[$levelId])) {
            $candidates[$levelId] = [
                'school_level_id' => $levelId,
                'name' => $levels[$levelId],
                'sources' => [],
            ];
        }

        if (! in_array($source, $candidates[$levelId]['sources'], true)) {
            $candidates[$levelId]['sources'][] = $source;
        }
    }

    protected function levelsForCompany($companyId)
    {
        if (! array_key_exists($companyId, $this->levelCache)) {
            $this->levelCache[$companyId] = DB::table('school_levels')
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->pluck('name', 'id')
                ->mapWithKeys(function ($name, $id) {
                    return [(int) $id => $name];
                })
                ->all();
        }

        return $this->levelCache[$companyId];
    }
}
