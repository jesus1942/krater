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
    use ResolvesSchoolLevel;

    public function authorize()
    {
        // Los modelos legacy usan request()->except(), no solo validated().
        // El cuerpo nunca puede cambiar la empresa o el nivel ya autorizado.
        foreach (['company_id' => TenantContext::companyId(), 'school_level_id' => $this->writeLevelId()] as $field => $expected) {
            if (($field !== 'school_level_id' || $this->levelRecord() || TenantContext::schoolLevelId() !== null) && $this->exists($field) && ($this->input($field) === null ? null : (int) $this->input($field)) !== $expected) {
                return false;
            }
        }
        if ($this->filled('user_id') && ! app(TenantUsers::class)->customers()->whereKey($this->input('user_id'))->exists()) {
            return false;
        }
        foreach (['invoice_id' => Invoice::class, 'student_id' => Student::class, 'enrollment_id' => Enrollment::class,
            'payment_method_id' => PaymentMethod::class, 'expense_category_id' => ExpenseCategory::class] as $field => $model) {
            if ($this->filled($field) && ! $model::where('company_id', TenantContext::companyId())->whereKey($this->input($field))
                ->when(in_array($field, ['invoice_id', 'student_id', 'enrollment_id']), fn ($q) => $q->where('school_level_id', $this->writeLevelId()))->exists()) {
                return false;
            }
        }
        if ($this->filled('family_member_id') && ! FamilyMember::where('company_id', TenantContext::companyId())
            ->whereKey($this->input('family_member_id'))
            ->when($this->writeLevelId() !== null, fn ($q) => $q->whereHas('students', fn ($students) => $students->where('school_level_id', $this->writeLevelId())))
            ->exists()) {
            return false;
        }

        // Los ítems vinculados también deben pertenecer al nivel del documento.
        foreach ($this->input('items', []) as $item) {
            if (! empty($item['item_id']) && ! \Crater\Models\Item::whereKey($item['item_id'])
                ->where('school_level_id', $this->writeLevelId())->exists()) return false;
        }

        return true;
    }
}
