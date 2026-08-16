<?php

namespace Crater\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    /**
     * La autorizacion la hacen el middleware `permission:` y la policy del
     * controlador. Devolver true aca no es el problema que tenian los 34
     * FormRequest heredados: aquellos eran el UNICO lugar donde podia haber
     * autorizacion, y no la hacian.
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = optional($this->route('academic_year'))->id;
        $levelId = $this->header('school-level');

        return [
            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                // Un solo ciclo por anio y por nivel. Sin esto se pueden crear
                // dos ciclos 2027 y la promocion no sabe a cual promover.
                Rule::unique('academic_years')
                    ->where(fn ($q) => $q->where('school_level_id', $levelId))
                    ->ignore($id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'closing', 'closed'])],
        ];
    }

    public function messages()
    {
        return [
            'year.unique' => 'Ya existe un ciclo lectivo de ese anio para este nivel.',
            'ends_on.after' => 'La fecha de fin tiene que ser posterior a la de inicio.',
        ];
    }
}
