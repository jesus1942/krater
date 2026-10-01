<?php

namespace Crater\Http\Requests\Concerns;

use Crater\Models\Invoice;
use Crater\Models\PaymentMethod;
use Crater\Models\ExpenseCategory;
use Crater\Models\Student;
use Crater\Models\Enrollment;
use Crater\Models\FamilyMember;
use Crater\Services\Access\TenantUsers;
use Crater\Support\TenantContext;

trait ValidatesFinanceTenant
{
    public function authorize()
    {
        // Los modelos legacy usan request()->except(), no solo validated().
        // El cuerpo nunca puede cambiar la empresa o el nivel ya autorizado.
        foreach (['company_id' => TenantContext::companyId(), 'school_level_id' => TenantContext::schoolLevelId()] as $field => $expected) {
            if ($this->exists($field) && ($this->input($field) === null ? null : (int) $this->input($field)) !== $expected) {
                return false;
            }
        }
        if ($this->filled('user_id') && ! app(TenantUsers::class)->customers()->whereKey($this->input('user_id'))->exists()) {
            return false;
        }
        foreach (['invoice_id' => Invoice::class, 'student_id' => Student::class, 'enrollment_id' => Enrollment::class,
            'payment_method_id' => PaymentMethod::class, 'expense_category_id' => ExpenseCategory::class] as $field => $model) {
            if ($this->filled($field) && ! $model::where('company_id', TenantContext::companyId())->whereKey($this->input($field))->exists()) {
                return false;
            }
        }
        if ($this->filled('family_member_id') && ! FamilyMember::where('company_id', TenantContext::companyId())
            ->whereKey($this->input('family_member_id'))
            ->when(TenantContext::schoolLevelId() !== null, fn ($q) => $q->whereHas('students'))
            ->exists()) {
            return false;
        }

        return true;
    }
}
