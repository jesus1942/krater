<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $companyId = $this->header('company');
        $studentId = optional($this->route('student'))->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'dni' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('students')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                })->ignore($studentId),
            ],
            'birth_date' => ['nullable', 'date'],
            'guardian_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)->where('role', 'customer');
                }),
            ],
            'level' => ['nullable', Rule::in(['Inicial', 'Primario', 'Secundario', 'Superior'])],
            'grade' => ['nullable', 'string', 'max:50'],
            'division' => ['nullable', 'string', 'max:20'],
            'school_year' => ['required', 'integer', 'min:2022', 'max:2100'],
            'status' => ['required', Rule::in(['active', 'pending', 'withdrawn', 'graduated'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
