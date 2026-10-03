<?php

namespace Crater\Services\Data;

use Crater\Models\AcademicYear;
use Crater\Models\AuditLog;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\GradeLevel;
use Crater\Models\Invoice;
use Crater\Models\Item;
use Crater\Models\Payment;
use Crater\Models\SchoolLevel;
use Crater\Models\Student;
use Crater\Models\StudyPlan;
use Crater\Models\Subject;
use Crater\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LevelReconciliationService
{
    const MODELS = [
        'StudyPlan' => ['class' => StudyPlan::class, 'label' => 'Planes de estudio'],
        'CourseSection' => ['class' => CourseSection::class, 'label' => 'Secciones de materia'],
        'GradeLevel' => ['class' => GradeLevel::class, 'label' => 'Cursos / años'],
        'Estimate' => ['class' => Estimate::class, 'label' => 'Presupuestos'],
        'AcademicYear' => ['class' => AcademicYear::class, 'label' => 'Ciclos lectivos'],
        'Student' => ['class' => Student::class, 'label' => 'Alumnos'],
        'Division' => ['class' => Division::class, 'label' => 'Divisiones'],
        'Item' => ['class' => Item::class, 'label' => 'Conceptos / artículos'],
        'Enrollment' => ['class' => Enrollment::class, 'label' => 'Matrículas'],
        'Payment' => ['class' => Payment::class, 'label' => 'Cobros'],
        'Invoice' => ['class' => Invoice::class, 'label' => 'Facturas'],
        'Expense' => ['class' => Expense::class, 'label' => 'Gastos'],
        'Subject' => ['class' => Subject::class, 'label' => 'Materias'],
    ];

    public function listOrphans(int $companyId): array
    {
        $groups = [];

        foreach (self::MODELS as $name => $meta) {
            $modelClass = $meta['class'];
            $model = new $modelClass();
            $table = $model->getTable();

            $records = $modelClass::withoutGlobalScopes()
                ->from($table)
                ->leftJoin('school_levels as reconciliation_levels', 'reconciliation_levels.id', '=', $table.'.school_level_id')
                ->where($table.'.company_id', $companyId)
                ->where(function ($query) use ($table) {
                    $query->whereNull($table.'.school_level_id')
                        ->orWhereNull('reconciliation_levels.id')
                        ->orWhereColumn('reconciliation_levels.company_id', '<>', $table.'.company_id');
                })
                ->select($table.'.*')
                ->orderBy($table.'.id')
                ->get()
                ->map(function ($record) use ($name, $companyId) {
                    return [
                        'id' => (int) $record->id,
                        'current_school_level_id' => $record->school_level_id === null
                            ? null
                            : (int) $record->school_level_id,
                        'orphan_reason' => $this->orphanReason($record->school_level_id, $companyId),
                        'reference' => $this->referenceFor($name, $record),
                    ];
                })
                ->values()
                ->all();

            if ($records) {
                $groups[] = [
                    'model' => $name,
                    'label' => $meta['label'],
                    'records' => $records,
                ];
            }
        }

        return $groups;
    }

    public function preview(string $modelName, array $ids, int $schoolLevelId, int $companyId): array
    {
        list($canonicalName, $modelClass) = $this->resolveModel($modelName);
        $target = $this->targetLevel($schoolLevelId, $companyId);

        $rows = [];
        foreach ($this->normalizeIds($ids) as $id) {
            $record = $this->findRecord($modelClass, $id, $companyId, false);
            $this->assertReconciliable($canonicalName, $record, $target, $companyId);

            $rows[] = [
                'model' => $canonicalName,
                'id' => (int) $record->id,
                'from_school_level_id' => $record->school_level_id === null
                    ? null
                    : (int) $record->school_level_id,
                'to_school_level_id' => (int) $target->id,
                'to_school_level_name' => $target->name,
                'reference' => $this->referenceFor($canonicalName, $record),
            ];
        }

        return $rows;
    }

    public function apply(
        string $modelName,
        array $ids,
        int $schoolLevelId,
        int $companyId,
        ?User $actor,
        string $reason
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('El motivo es obligatorio.');
        }

        list($canonicalName, $modelClass) = $this->resolveModel($modelName);
        $ids = $this->normalizeIds($ids);

        return DB::transaction(function () use (
            $canonicalName,
            $modelClass,
            $ids,
            $schoolLevelId,
            $companyId,
            $actor,
            $reason
        ) {
            $target = $this->targetLevel($schoolLevelId, $companyId, true);
            $results = [];

            foreach ($ids as $id) {
                $record = $this->findRecord($modelClass, $id, $companyId, true);
                $this->assertReconciliable($canonicalName, $record, $target, $companyId);

                $oldLevelId = $record->school_level_id === null
                    ? null
                    : (int) $record->school_level_id;

                $modelClass::withoutGlobalScopes()
                    ->whereKey($record->id)
                    ->where('company_id', $companyId)
                    ->update(['school_level_id' => $target->id]);

                $request = app()->bound('request') ? request() : null;

                AuditLog::create([
                    'company_id' => $companyId,
                    'school_level_id' => $target->id,
                    'user_id' => optional($actor)->id,
                    'action' => 'level_reassigned',
                    'auditable_type' => $modelClass,
                    'auditable_id' => $record->id,
                    'old_values' => ['school_level_id' => $oldLevelId],
                    'new_values' => [
                        'school_level_id' => (int) $target->id,
                        'reason' => $reason,
                        'source' => 'level_reconciliation',
                    ],
                    'ip_address' => $request ? $request->ip() : null,
                    'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
                    'severity' => AuditLog::SEVERITY_HIGH,
                    'created_at' => now(),
                ]);

                $results[] = [
                    'model' => $canonicalName,
                    'id' => (int) $record->id,
                    'from_school_level_id' => $oldLevelId,
                    'to_school_level_id' => (int) $target->id,
                ];
            }

            return $results;
        });
    }

    public function levels(int $companyId): array
    {
        return SchoolLevel::query()
            ->where('company_id', $companyId)
            ->where('enabled', true)
            ->orderBy('id')
            ->get(['id', 'name', 'code'])
            ->map(function ($level) {
                return [
                    'id' => (int) $level->id,
                    'name' => $level->name,
                    'code' => $level->code,
                ];
            })
            ->all();
    }

    public function modelCatalog(): array
    {
        return collect(self::MODELS)
            ->map(function ($meta, $name) {
                return ['model' => $name, 'label' => $meta['label']];
            })
            ->values()
            ->all();
    }

    protected function resolveModel(string $input): array
    {
        $needle = strtolower(trim($input));

        foreach (self::MODELS as $name => $meta) {
            $class = $meta['class'];
            $table = (new $class())->getTable();

            if (in_array($needle, [
                strtolower($name),
                strtolower(class_basename($class)),
                strtolower($table),
            ], true)) {
                return [$name, $class];
            }
        }

        throw new InvalidArgumentException('Modelo no permitido para reconciliación.');
    }

    protected function targetLevel(int $schoolLevelId, int $companyId, bool $lock = false): SchoolLevel
    {
        $query = SchoolLevel::query()
            ->where('company_id', $companyId)
            ->where('enabled', true)
            ->whereKey($schoolLevelId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $level = $query->first();
        if (! $level) {
            throw new InvalidArgumentException('El nivel destino no pertenece a la empresa o está deshabilitado.');
        }

        return $level;
    }

    protected function findRecord(string $modelClass, int $id, int $companyId, bool $lock): Model
    {
        $query = $modelClass::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereKey($id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $record = $query->first();
        if (! $record) {
            throw new InvalidArgumentException('No existe el registro #'.$id.' en esta empresa.');
        }

        return $record;
    }

    protected function assertReconciliable(
        string $modelName,
        Model $record,
        SchoolLevel $target,
        int $companyId
    ): void {
        $reason = $this->orphanReason($record->school_level_id, $companyId);

        if ($reason === 'valid_level') {
            throw new InvalidArgumentException(
                $modelName.' #'.$record->id.' ya tiene un nivel válido. La reconciliación no lo pisa.'
            );
        }

        $linkedLevels = $this->linkedCanonicalLevels($modelName, $record, $companyId);

        foreach ($linkedLevels as $source => $linkedLevelId) {
            if ((int) $linkedLevelId !== (int) $target->id) {
                throw new InvalidArgumentException(
                    $modelName.' #'.$record->id.' contradice '.$source.
                    ': ese vínculo canónico pertenece al nivel #'.$linkedLevelId.'.'
                );
            }
        }
    }

    protected function linkedCanonicalLevels(string $modelName, Model $record, int $companyId): array
    {
        $checks = [];

        if ($modelName === 'CourseSection') {
            $checks = [
                ['academic_years', $record->academic_year_id ?? null, 'ciclo lectivo'],
                ['divisions', $record->division_id ?? null, 'división'],
                ['subjects', $record->subject_id ?? null, 'materia'],
            ];
        } elseif ($modelName === 'Division') {
            $checks = [
                ['academic_years', $record->academic_year_id ?? null, 'ciclo lectivo'],
                ['grade_levels', $record->grade_level_id ?? null, 'curso/año'],
            ];
        } elseif ($modelName === 'Enrollment') {
            $checks = [
                ['academic_years', $record->academic_year_id ?? null, 'ciclo lectivo'],
                ['divisions', $record->division_id ?? null, 'división'],
                ['students', $record->student_id ?? null, 'alumno'],
            ];
        } elseif ($modelName === 'Subject') {
            $checks = [
                ['study_plans', $record->study_plan_id ?? null, 'plan de estudios'],
            ];
        } elseif ($modelName === 'Student') {
            $levels = DB::table('enrollments')
                ->where('company_id', $companyId)
                ->where('student_id', $record->id)
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->whereNotNull('school_level_id')
                ->pluck('school_level_id')
                ->unique()
                ->values();

            foreach ($levels as $index => $levelId) {
                $checks[] = [null, null, 'matrícula activa '.($index + 1), (int) $levelId];
            }
        } elseif ($modelName === 'Invoice') {
            $checks = [
                ['enrollments', $record->enrollment_id ?? null, 'matrícula'],
                ['students', $record->student_id ?? null, 'alumno'],
            ];
        } elseif ($modelName === 'Payment') {
            $checks = [
                ['invoices', $record->invoice_id ?? null, 'factura'],
                ['students', $record->student_id ?? null, 'alumno'],
            ];
        }

        $result = [];
        foreach ($checks as $check) {
            if (isset($check[3])) {
                $result[$check[2]] = $check[3];
                continue;
            }

            list($table, $id, $label) = $check;
            if (! $id) {
                continue;
            }

            $linked = DB::table($table)
                ->where('company_id', $companyId)
                ->where('id', $id)
                ->whereNotNull('school_level_id')
                ->first(['school_level_id']);

            if ($linked) {
                $result[$label] = (int) $linked->school_level_id;
            }
        }

        return $result;
    }

    protected function orphanReason($schoolLevelId, int $companyId): string
    {
        if ($schoolLevelId === null) {
            return 'null_level';
        }

        $level = DB::table('school_levels')->where('id', $schoolLevelId)->first(['company_id']);

        if (! $level) {
            return 'missing_level';
        }

        if ((int) $level->company_id !== $companyId) {
            return 'foreign_company_level';
        }

        return 'valid_level';
    }

    protected function normalizeIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_map('intval', $ids)));
        $normalized = array_values(array_filter($normalized, function ($id) {
            return $id > 0;
        }));

        if (! $normalized) {
            throw new InvalidArgumentException('Debe indicar al menos un ID válido.');
        }

        return $normalized;
    }

    protected function referenceFor(string $modelName, Model $record): array
    {
        if ($modelName === 'Estimate') {
            return [
                'number' => $record->estimate_number ?? null,
                'date' => isset($record->estimate_date) ? (string) $record->estimate_date : null,
                'total' => isset($record->total) ? (int) $record->total : null,
            ];
        }

        if ($modelName === 'Invoice') {
            return [
                'number' => $record->invoice_number ?? null,
                'date' => isset($record->invoice_date) ? (string) $record->invoice_date : null,
                'total' => isset($record->total) ? (int) $record->total : null,
            ];
        }

        if ($modelName === 'Payment') {
            return [
                'number' => $record->payment_number ?? null,
                'date' => isset($record->payment_date) ? (string) $record->payment_date : null,
                'amount' => isset($record->amount) ? (int) $record->amount : null,
            ];
        }

        if ($modelName === 'Expense') {
            return [
                'date' => isset($record->expense_date) ? (string) $record->expense_date : null,
                'amount' => isset($record->amount) ? (int) $record->amount : null,
            ];
        }

        if ($modelName === 'Student') {
            return ['full_name' => $record->full_name ?? null];
        }

        if ($modelName === 'Item') {
            return ['name' => $record->name ?? null];
        }

        if (isset($record->name)) {
            return ['name' => $record->name];
        }

        return [];
    }
}
