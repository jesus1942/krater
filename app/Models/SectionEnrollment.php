<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inscripcion a una materia concreta.
 *
 * Normalmente se deriva de la matricula, pero se materializa porque en
 * Terciario un alumno cursa materias sueltas de distintos anios, y porque un
 * recursante cursa solo las que debe.
 */
class SectionEnrollment extends Model
{
    protected $guarded = ['id'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
