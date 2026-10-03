<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    use \Crater\Http\Requests\Concerns\ValidatesFinanceTenant;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return array_merge($this->schoolLevelRules(), [
            'expense_date' => [
                'required',
            ],
            'expense_category_id' => [
                'required',
            ],
            'amount' => [
                'required',
            ],
            'user_id' => [
                'nullable',
            ],
            'notes' => [
                'nullable',
            ],
        ]);
    }
}
