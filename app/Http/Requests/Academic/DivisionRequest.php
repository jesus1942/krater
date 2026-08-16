<?php

namespace Crater\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DivisionRequest extends FormRequest
{
    public function authorize()
    {
        return true;   // la autorizacion la hacen el middleware y la policy
    }

    public function rules()
    {
        $id = optional($this->route('division'))->id;

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'name' => [
                'required',
                'string',
                'max:50',
                // No puede haber dos "3.er anio A" en el mismo ciclo.
                Rule::unique('divisions')
                    ->where(fn ($q) => $q
                        ->where('academic_year_id', $this->academic_year_id)
                        ->where('grade_level_id', $this->grade_level_id))
                    ->ignore($id),
            ],
            'shift' => ['nullable', Rule::in(['morning', 'afternoon', 'evening'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'head_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages()
    {
        return [
            'name.unique' => 'Ya existe una division con ese nombre para ese curso y ciclo.',
        ];
    }
}
