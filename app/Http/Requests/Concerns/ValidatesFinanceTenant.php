<?php

namespace Crater\Http\Requests\Concerns;

use Crater\Models\Invoice;
use Crater\Models\PaymentMethod;
use Crater\Models\ExpenseCategory;
use Crater\Services\Access\TenantUsers;
use Crater\Support\TenantContext;

trait ValidatesFinanceTenant
{
    public function authorize()
    {
        if ($this->filled('user_id') && ! app(TenantUsers::class)->customers()->whereKey($this->input('user_id'))->exists()) {
            return false;
        }
        foreach (['invoice_id' => Invoice::class, 'payment_method_id' => PaymentMethod::class, 'expense_category_id' => ExpenseCategory::class] as $field => $model) {
            if ($this->filled($field) && ! $model::where('company_id', TenantContext::companyId())->whereKey($this->input($field))->exists()) {
                return false;
            }
        }

        return true;
    }
}
