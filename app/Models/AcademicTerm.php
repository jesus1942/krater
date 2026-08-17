<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Periodo del ciclo: trimestre, cuatrimestre o bimestre.
 *
 * `grading_opens_at` y `grading_closes_at` definen la ventana en que los
 * docentes pueden cargar notas. Fuera de esa ventana hace falta el permiso
 * agravado `grading.grade.amend_closed`.
 */
class AcademicTerm extends Model
{
    const KIND_TERM = 'term';
    const KIND_SEMESTER = 'semester';
    const KIND_BIMESTER = 'bimester';
    const KIND_FINAL = 'final';

    protected $guarded = ['id'];

    protected $casts = [
        'position' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'grading_opens_at' => 'datetime',
        'grading_closes_at' => 'datetime',
        'is_closed' => 'boolean',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * La carga de notas esta habilitada ahora mismo.
     *
     * Si no hay ventana configurada se considera abierta mientras el periodo no
     * este cerrado: no se traba a la escuela por no haber cargado fechas.
     */
    public function isGradingOpen(): bool
    {
        if ($this->is_closed) {
            return false;
        }

        $ahora = now();

        if ($this->grading_opens_at && $ahora->lt($this->grading_opens_at)) {
            return false;
        }

        if ($this->grading_closes_at && $ahora->gt($this->grading_closes_at)) {
            return false;
        }

        return true;
    }
}
