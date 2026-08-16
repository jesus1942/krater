<?php

namespace Crater\Http\Requests\Academic;

use Crater\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize()
    {
        return true;   // autorizan el middleware y la policy
    }

    public function rules()
    {
        $companyId = TenantContext::companyId();
        $levelId = TenantContext::schoolLevelId();
        $id = optional($this->route('subject'))->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects')
                    ->where(fn ($q) => $q
                        ->where('company_id', $companyId)
                        ->where('school_level_id', $levelId)
                        ->where('study_plan_id', $this->study_plan_id))
                    ->ignore($id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'study_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('study_plans', 'id')->where(fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('school_level_id', $levelId)),
            ],
            'year_of_plan' => ['nullable', 'integer', 'min:1', 'max:10'],
            'weekly_hours' => ['nullable', 'integer', 'min:1', 'max:40'],
            'total_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'duration' => [
                'required',
                Rule::in(['annual', 'first_semester', 'second_semester', 'modular']),
            ],
            'counts_for_promotion' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages()
    {
        return [
            'name.unique' => 'Ya existe una materia con ese nombre en ese plan de estudios.',
        ];
    }
}
