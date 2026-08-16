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
            'school_level_id' => [
                'required',
                Rule::exists('school_levels', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)->where('enabled', true);
                }),
            ],
            'level' => ['nullable', Rule::in(['Primario', 'Secundario', 'Terciario'])],
            'grade' => ['nullable', 'string', 'max:50'],
            'division' => ['nullable', 'string', 'max:20'],
            'school_year' => ['required', 'integer', 'min:2022', 'max:2100'],
            'status' => ['required', Rule::in(['active', 'pending', 'withdrawn', 'graduated'])],
            'notes' => ['nullable', 'string', 'max:2000'],

            'family_members' => ['nullable', 'array'],
            'family_members.*.id' => ['nullable', 'integer'],
            'family_members.*.name' => ['required', 'string', 'max:190'],
            'family_members.*.dni' => ['nullable', 'string', 'max:30'],
            'family_members.*.email' => ['nullable', 'email', 'max:190'],
            'family_members.*.phone' => ['nullable', 'string', 'max:60'],
            'family_members.*.relationship' => ['nullable', 'string', 'max:40'],
            'family_members.*.is_responsible' => ['nullable', 'boolean'],
            'family_members.*.is_financial_responsible' => ['nullable', 'boolean'],
            'family_members.*.is_primary_contact' => ['nullable', 'boolean'],
        ];
    }
}
