<?php

namespace Crater\Http\Controllers\V1\Staff;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Models\PayrollPayment;
use Crater\Models\PayrollPeriod;
use Crater\Models\PayrollSlip;
use Crater\Models\SchoolLevel;
use Crater\Models\StaffMember;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    protected AccessManager $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    public function index(Request $request)
    {
        $this->assertCan($request, Permission::HR_PAYROLL_VIEW);
        $companyId = (int) TenantContext::companyId();
        $levelId = TenantContext::schoolLevelId();
        $global = $this->access->isTotalAdmin($request->user());

        $periods = PayrollPeriod::query()
            ->where('company_id', $companyId)
            ->when(! $global || $levelId, fn ($q) => $q->where('school_level_id', $levelId))
            ->with(['schoolLevel:id,name,code', 'slips' => function ($q) {
                $q->with(['staffMember:id,first_name,last_name,staff_category', 'payments' => function ($p) {
                    $p->orderByDesc('paid_at')->orderByDesc('id');
                }]);
            }])
            ->orderByDesc('year')->orderByDesc('month')->get();

        $levels = SchoolLevel::query()
            ->where('company_id', $companyId)->where('enabled', true)
            ->when(! $global, fn ($q) => $q->whereKey($levelId))
            ->get(['id', 'name', 'code']);

        $staff = StaffMember::query()
            ->where('company_id', $companyId)
            ->where('employment_status', 'active')
            ->whereHas('assignments', function ($q) use ($global, $levelId) {
                $q->where('active', true);
                if (! $global || $levelId) {
                    $q->where('school_level_id', $levelId);
                }
            })
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'staff_category']);

        return response()->json([
            'data' => $periods,
            'levels' => $levels,
            'staff' => $staff,
            'global' => $global,
        ]);
    }

    public function storePeriod(Request $request)
    {
        $this->assertCan($request, Permission::HR_PAYROLL_MANAGE);
        $data = $request->validate([
            'school_level_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string'],
        ]);
        $levelId = $this->assertLevelAllowed($request, (int) $data['school_level_id']);

        $exists = PayrollPeriod::where('company_id', TenantContext::companyId())
            ->where('school_level_id', $levelId)
            ->where('year', $data['year'])->where('month', $data['month'])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['month' => ['Ya existe un período de liquidación para ese nivel y mes.']]);
        }

        $period = PayrollPeriod::create([
            'company_id' => TenantContext::companyId(),
            'school_level_id' => $levelId,
            'year' => $data['year'],
            'month' => $data['month'],
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $period->load('schoolLevel')], 201);
    }

    public function storeSlip(Request $request, PayrollPeriod $payrollPeriod)
    {
        $this->assertPeriod($request, $payrollPeriod);
        $this->assertCan($request, Permission::HR_PAYROLL_MANAGE);
        abort_if($payrollPeriod->status !== 'draft', 422, 'El período ya no admite nuevas liquidaciones.');

        $data = $request->validate([
            'staff_member_id' => ['required', 'integer'],
            'gross_amount' => ['required', 'integer', 'min:0'],
            'deductions_amount' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        if ($data['deductions_amount'] > $data['gross_amount']) {
            throw ValidationException::withMessages(['deductions_amount' => ['Los descuentos no pueden superar el bruto.']]);
        }

        $member = StaffMember::where('company_id', TenantContext::companyId())
            ->whereKey($data['staff_member_id'])->firstOrFail();
        $eligible = $member->assignments()->where('active', true)
            ->where('school_level_id', $payrollPeriod->school_level_id)->exists();
        if (! $eligible) {
            throw ValidationException::withMessages(['staff_member_id' => ['La persona no tiene un cargo activo en este nivel.']]);
        }

        $slip = PayrollSlip::updateOrCreate(
            ['payroll_period_id' => $payrollPeriod->id, 'staff_member_id' => $member->id],
            [
                'company_id' => TenantContext::companyId(),
                'school_level_id' => $payrollPeriod->school_level_id,
                'status' => 'draft',
                'gross_amount' => $data['gross_amount'],
                'deductions_amount' => $data['deductions_amount'],
                'net_amount' => $data['gross_amount'] - $data['deductions_amount'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json(['data' => $slip->load('staffMember')]);
    }

    public function approveSlip(Request $request, PayrollSlip $payrollSlip)
    {
        $this->assertSlip($request, $payrollSlip);
        $this->assertCan($request, Permission::HR_PAYROLL_APPROVE);
        abort_if(! in_array($payrollSlip->status, ['draft', 'approved'], true), 422, 'La liquidación no puede aprobarse en su estado actual.');
        $payrollSlip->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        return response()->json(['data' => $payrollSlip->fresh()]);
    }

    public function storePayment(Request $request, PayrollSlip $payrollSlip)
    {
        $this->assertSlip($request, $payrollSlip);
        $this->assertCan($request, Permission::HR_PAYROLL_PAY);
        abort_unless(in_array($payrollSlip->status, ['approved', 'partially_paid'], true), 422, 'La liquidación debe estar aprobada antes de pagarla.');

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['transfer', 'cash', 'check', 'other'])],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string'],
        ]);

        $paid = (int) $payrollSlip->activePayments()->sum('amount');
        $outstanding = (int) $payrollSlip->net_amount - $paid;
        if ($data['amount'] > $outstanding) {
            throw ValidationException::withMessages(['amount' => ['El pago supera el saldo pendiente.']]);
        }

        $payment = DB::transaction(function () use ($request, $payrollSlip, $data, $paid) {
            $payment = $payrollSlip->payments()->create([
                'company_id' => $payrollSlip->company_id,
                'school_level_id' => $payrollSlip->school_level_id,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $newPaid = $paid + (int) $data['amount'];
            $payrollSlip->update(['status' => $newPaid >= (int) $payrollSlip->net_amount ? 'paid' : 'partially_paid']);
            return $payment;
        });

        return response()->json(['data' => $payment], 201);
    }

    public function reversePayment(Request $request, PayrollPayment $payrollPayment)
    {
        $this->assertPayment($request, $payrollPayment);
        $this->assertCan($request, Permission::HR_PAYROLL_PAY);
        abort_if($payrollPayment->reversed_at, 422, 'El pago ya fue revertido.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        DB::transaction(function () use ($request, $payrollPayment, $data) {
            $payrollPayment->update([
                'reversed_at' => now(),
                'reversed_by' => $request->user()->id,
                'reversal_reason' => $data['reason'],
            ]);
            $slip = $payrollPayment->slip()->firstOrFail();
            $paid = (int) $slip->activePayments()->sum('amount');
            $slip->update(['status' => $paid <= 0 ? 'approved' : ($paid >= (int) $slip->net_amount ? 'paid' : 'partially_paid')]);
        });

        return response()->json(['data' => $payrollPayment->fresh()]);
    }

    protected function assertCan(Request $request, string $permission): void
    {
        $levelId = TenantContext::schoolLevelId();
        if ($this->access->allows($request->user(), $permission, $levelId)) {
            return;
        }
        if ($this->access->isTotalAdmin($request->user()) && $this->access->allows($request->user(), $permission, null)) {
            return;
        }
        abort(403);
    }

    protected function assertLevelAllowed(Request $request, int $levelId): int
    {
        SchoolLevel::where('company_id', TenantContext::companyId())->whereKey($levelId)->where('enabled', true)->firstOrFail();
        if ($this->access->isTotalAdmin($request->user())) {
            return $levelId;
        }
        abort_unless((int) TenantContext::schoolLevelId() === $levelId, 403);
        return $levelId;
    }

    protected function assertPeriod(Request $request, PayrollPeriod $period): void
    {
        abort_unless((int) $period->company_id === (int) TenantContext::companyId(), 404);
        $this->assertLevelAllowed($request, (int) $period->school_level_id);
    }

    protected function assertSlip(Request $request, PayrollSlip $slip): void
    {
        abort_unless((int) $slip->company_id === (int) TenantContext::companyId(), 404);
        $this->assertLevelAllowed($request, (int) $slip->school_level_id);
    }

    protected function assertPayment(Request $request, PayrollPayment $payment): void
    {
        abort_unless((int) $payment->company_id === (int) TenantContext::companyId(), 404);
        $this->assertLevelAllowed($request, (int) $payment->school_level_id);
    }
}
