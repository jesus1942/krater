<?php

namespace Crater\Http\Controllers\V1\Dashboard;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\SchoolLevel;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inicio de la escuela: matricula, ocupacion de divisiones, personal y
 * cobranza de cuotas. Reemplaza el tablero de facturacion heredado de Crater.
 *
 * Respeta el nivel del encabezado (scopes globales) y muestra cada bloque solo
 * si el usuario tiene el permiso correspondiente en ese nivel: la direccion
 * ve matricula y personal; cuotas y cobranza exigen finance.view.
 */
class SchoolDashboardController extends Controller
{
    const LEVEL_LABELS = ['primary' => 'Primario', 'secondary' => 'Secundario', 'tertiary' => 'Terciario'];

    public function __construct()
    {
        $this->middleware('tenant');
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $level = TenantContext::schoolLevelId();
        $access = app(AccessManager::class);

        $academic = $access->seesLevelEnrollment($user, $level);
        $staff = $access->allows($user, Permission::HR_STAFF_VIEW, $level);
        // Sin ningun permiso de finanzas no hay cuotas ni cobranza en el inicio.
        $finance = $access->allows($user, Permission::FINANCE_VIEW, $level);
        abort_unless($academic || $finance, 403);

        return response()->json([
            'school_level' => $level ? $this->levelLabel(SchoolLevel::find($level)) : null,
            'academic' => $academic ? $this->academic($level === null) : null,
            'staff' => $staff ? $this->staff((int) TenantContext::companyId(), $level) : null,
            'finance' => $finance ? $this->finance() : null,
        ]);
    }

    protected function academic(bool $institutional): array
    {
        $divisions = Division::query()->where('divisions.enabled', true)
            ->join('academic_years', 'academic_years.id', '=', 'divisions.academic_year_id')
            ->whereIn('academic_years.status', ['active', 'closing'])
            ->join('grade_levels', 'grade_levels.id', '=', 'divisions.grade_level_id')
            ->leftJoin('school_levels', 'school_levels.id', '=', 'divisions.school_level_id')
            ->orderBy('school_levels.id')->orderBy('grade_levels.position')->orderBy('divisions.name')
            ->get(['divisions.id', 'divisions.name', 'divisions.shift', 'divisions.capacity',
                'grade_levels.name as grade', 'school_levels.code as level_code', 'school_levels.name as level_name',
                'academic_years.year']);

        $enrolled = Enrollment::query()->where('status', Enrollment::STATUS_ACTIVE)
            ->whereIn('division_id', $divisions->pluck('id'))
            ->groupBy('division_id')->selectRaw('division_id, count(*) as total')->pluck('total', 'division_id');

        $rows = $divisions->map(fn ($d) => [
            'id' => $d->id,
            'level' => $institutional ? (self::LEVEL_LABELS[$d->level_code] ?? $d->level_name) : null,
            'grade' => $d->grade, 'division' => $d->name, 'shift' => $d->shift,
            'enrolled' => (int) ($enrolled[$d->id] ?? 0), 'capacity' => $d->capacity ? (int) $d->capacity : null,
        ])->values();
        $capacity = $rows->whereNotNull('capacity')->sum('capacity');

        return [
            'year' => $divisions->max('year'),
            'students' => $rows->sum('enrolled'),
            'divisions_count' => $rows->count(),
            'capacity' => $capacity ?: null,
            'occupied_with_capacity' => $rows->whereNotNull('capacity')->sum('enrolled'),
            'divisions' => $rows,
        ];
    }

    protected function staff(int $companyId, ?int $level): array
    {
        $query = DB::table('staff_members')->where('staff_members.company_id', $companyId)
            ->where('employment_status', 'active');
        if ($level) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('staff_assignments')
                ->whereColumn('staff_assignments.staff_member_id', 'staff_members.id')
                ->where('staff_assignments.active', true)
                ->where(fn ($w) => $w->where('staff_assignments.school_level_id', $level)->orWhereNull('staff_assignments.school_level_id')));
        }

        return ['active' => $query->count()];
    }

    protected function finance(): array
    {
        $today = now()->toDateString();
        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();
        $issued = Invoice::query()->where('status', '!=', Invoice::STATUS_DRAFT);

        $overdue = (clone $issued)->where('due_amount', '>', 0)->whereDate('due_date', '<', $today);
        $families = (clone $overdue)->select('user_id', DB::raw('sum(due_amount) as amount'), DB::raw('count(*) as fees'),
            DB::raw('min(due_date) as oldest'))
            ->groupBy('user_id')->orderByDesc('amount')->limit(6)->get();
        $names = DB::table('users')->whereIn('id', $families->pluck('user_id'))->pluck('name', 'id');

        $billedMonth = (int) (clone $issued)->whereBetween('invoice_date', [$from, $to])->sum('total');
        $collectedMonth = (int) Payment::query()->whereBetween('payment_date', [$from, $to])->sum('amount');

        return [
            'due_total' => (int) (clone $issued)->sum('due_amount'),
            'overdue_total' => (int) (clone $overdue)->sum('due_amount'),
            'families_overdue' => (clone $overdue)->distinct()->count('user_id'),
            'month' => ['billed' => $billedMonth, 'collected' => $collectedMonth,
                'rate' => $billedMonth > 0 ? (int) round(min(100, $collectedMonth * 100 / $billedMonth)) : null],
            'top_overdue' => $families->map(fn ($f) => [
                'customer_id' => (int) $f->user_id, 'name' => $names[$f->user_id] ?? 'Responsable sin nombre',
                'amount' => (int) $f->amount, 'fees' => (int) $f->fees, 'oldest' => substr((string) $f->oldest, 0, 10),
            ])->values(),
        ];
    }

    protected function levelLabel(?SchoolLevel $level): ?string
    {
        return $level ? (self::LEVEL_LABELS[$level->code] ?? $level->name) : null;
    }
}
