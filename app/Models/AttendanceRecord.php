<?php

namespace Crater\Models;

use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Asistencia de un alumno en una fecha: por division (toma diaria) o por
 * seccion de materia (por clase), segun el modo del nivel.
 */
class AttendanceRecord extends Model
{
    use BelongsToSchoolLevel;

    const PRESENT = 'present';
    const ABSENT = 'absent';
    const LATE = 'late';

    const STATUSES = [self::PRESENT, self::ABSENT, self::LATE];

    /** Peso de cada estado en el computo de inasistencias. */
    const WEIGHTS = [self::PRESENT => 0, self::ABSENT => 1, self::LATE => 0.5];

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'absence_weight' => 'float',
        'is_justified' => 'boolean',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
