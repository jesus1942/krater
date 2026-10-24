<?php

namespace Crater\Http\Requests\Concerns;

use Crater\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Resuelve el nivel de escritura sin alterar el contexto de lectura global. */
trait ResolvesSchoolLevel
{
    /** El binding ya respeta empresa y nivel; nunca se busca sin scopes. */
    protected function levelRecord(): ?Model
    {
        foreach (['item', 'invoice', 'estimate', 'payment', 'expense', 'student'] as $parameter) {
            $record = $this->route($parameter);
            if ($record instanceof Model) {
                return $record;
            }
        }
        return null;
    }

    /** En edición manda el registro; en alta manda el nivel activo o elegido. */
    protected function writeLevelId(): ?int
    {
        $record = $this->levelRecord();
        $value = $record ? $record->school_level_id
            : (TenantContext::schoolLevelId() ?? $this->input('school_level_id'));
        return $value === null || $value === '' ? null : (int) $value;
    }

    /** Completa solamente un nivel omitido; una inyección se valida/rechaza. */
    protected function prepareForValidation(): void
    {
        if (! $this->levelRecord() && TenantContext::schoolLevelId() === null) {
            // Falta de nivel en el formulario es validación, no un 403 de vínculos.
            \Illuminate\Support\Facades\Validator::make($this->only('school_level_id'), $this->schoolLevelRules())->validate();
        }
        if (! $this->exists('school_level_id') && ($this->levelRecord() || TenantContext::schoolLevelId() !== null)) {
            $this->merge(['school_level_id' => $this->writeLevelId()]);
        }
    }

    /** Las altas globales requieren un nivel habilitado de la empresa actual. */
    protected function schoolLevelRules(): array
    {
        $record = $this->levelRecord();
        if ($record) {
            return ['school_level_id' => [$record->school_level_id === null ? 'nullable' : 'required', 'integer', function ($attribute, $value, $fail) use ($record) {
                if (($value === null ? null : (int) $value) !== ($record->school_level_id === null ? null : (int) $record->school_level_id)) {
                    $fail('El nivel del registro solo se puede cambiar mediante reubicación.');
                }
            }]];
        }
        return ['school_level_id' => ['required', 'integer', function ($attribute, $value, $fail) {
            if (TenantContext::schoolLevelId() !== null && (int) $value !== TenantContext::schoolLevelId()) {
                $fail('El nivel debe coincidir con el nivel institucional activo.');
            }
        }, Rule::exists('school_levels', 'id')
            ->where('company_id', TenantContext::companyId())->where('enabled', true)]];
    }
}
