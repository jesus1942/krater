<?php

namespace Crater\Models;

use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Matricula de un estudiante en una division, para un ciclo lectivo.
 *
 * Es el registro que se promociona al cerrar el anio. No se borra nunca: se le
 * cambia el estado, porque el paso de un alumno por la escuela tiene que quedar
 * registrado aunque se haya ido en mayo.
 */
class Enrollment extends Model
{
    use BelongsToSchoolLevel;

    const STATUS_ACTIVE = 'active';
    const STATUS_TRANSFERRED_OUT = 'transferred_out';
    const STATUS_WITHDRAWN = 'withdrawn';
    const STATUS_COMPLETED = 'completed';

    protected $guarded = ['id'];

    protected $casts = [
        'enrolled_on' => 'date',
        'left_on' => 'date',
        'has_curricular_adaptation' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function sectionEnrollments()
    {
        return $this->hasMany(SectionEnrollment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
