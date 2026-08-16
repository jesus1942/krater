<?php

namespace Crater\Http\Requests\Academic;

use Crater\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $companyId = TenantContext::companyId();
        $levelId = TenantContext::schoolLevelId();

        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where(fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('school_level_id', $levelId)),
            ],
            'enrolled_on' => ['nullable', 'date'],
            'attendance_condition' => ['nullable', Rule::in(['regular', 'libre', 'oyente'])],
            'has_curricular_adaptation' => ['sometimes', 'boolean'],
        ];
    }

    public function messages()
    {
        return [
            'student_id.exists' => 'El estudiante no pertenece a esta institución.',
            'division_id.exists' => 'La división no pertenece a este nivel.',
        ];
    }
}
