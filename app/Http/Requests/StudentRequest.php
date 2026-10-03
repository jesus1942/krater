<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    use \Crater\Http\Requests\Concerns\ResolvesSchoolLevel;
    protected function prepareForValidation(): void
    {
        if (app(\Crater\Services\Access\AccessManager::class)->allows($this->user(),
            \Crater\Enums\Permission::STUDENT_MANAGE, \Crater\Support\TenantContext::schoolLevelId())
            && ! $this->exists('school_level_id') && ($this->levelRecord() || \Crater\Support\TenantContext::schoolLevelId() !== null)) {
            $this->merge(['school_level_id' => $this->writeLevelId()]);
        }
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $companyId = $this->header('company');
        $studentId = optional($this->route('student'))->id;

        $rules = [
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
            // La ubicación académica se elige por IDs canónicos. El nivel,
            // nombre del curso, división y año se derivan en backend.
            'academic_year_id' => ['required', 'integer'],
            'grade_level_id' => ['required', 'integer'],
            'division_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['active', 'pending', 'withdrawn', 'graduated'])],
            'notes' => ['nullable', 'string', 'max:2000'],

            'family_members' => ['nullable', 'array'],
            'family_members.*.id' => ['nullable', 'integer'],
            'family_members.*.user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)->where('role', 'customer');
                }),
            ],
            'family_members.*.name' => ['required', 'string', 'max:190'],
            'family_members.*.dni' => ['nullable', 'string', 'max:30'],
            'family_members.*.email' => ['nullable', 'email', 'max:190'],
            'family_members.*.phone' => ['nullable', 'string', 'max:60'],
            'family_members.*.relationship' => ['nullable', 'string', 'max:40'],
            'family_members.*.is_responsible' => ['nullable', 'boolean'],
            'family_members.*.is_financial_responsible' => ['nullable', 'boolean'],
            'family_members.*.is_primary_contact' => ['nullable', 'boolean'],
        ];

        if ($studentId) {
            foreach (['academic_year_id', 'grade_level_id', 'division_id'] as $field) {
                $rules[$field] = ['nullable', 'integer'];
            }
        }

        // La habilitacion delegada nunca usa el formulario completo del legajo.
        if (! app(\Crater\Services\Access\AccessManager::class)->allows($this->user(),
            \Crater\Enums\Permission::STUDENT_MANAGE, \Crater\Support\TenantContext::schoolLevelId())) {
            $allowed = ['first_name', 'last_name', 'dni', 'birth_date'];
            if (! $studentId) {
                $allowed = array_merge($allowed, ['academic_year_id', 'grade_level_id', 'division_id', 'status']);
                $rules['status'] = ['sometimes', Rule::in(['active', 'pending'])];
            }
            abort_if(array_diff(array_keys($this->all()), $allowed), 403, 'La habilitacion permite solo datos basicos.');
            $rules = array_intersect_key($rules, array_flip($allowed));
        }
        if (app(\Crater\Services\Access\AccessManager::class)->allows($this->user(),
            \Crater\Enums\Permission::STUDENT_MANAGE, \Crater\Support\TenantContext::schoolLevelId())) {
            $rules = array_merge($rules, $this->schoolLevelRules());
            $rules['company_id'] = ['sometimes', 'integer', Rule::in([$companyId])];
        }
        return $rules;
    }
}
