<?php

namespace Crater\Models;

use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan de estudios versionado.
 *
 * Se versiona porque un alumno que ingreso en 2024 termina con el plan 2024
 * aunque en 2026 haya otro vigente.
 */
class StudyPlan extends Model
{
    use BelongsToSchoolLevel;

    protected $guarded = ['id'];

    protected $casts = [
        'effective_from_year' => 'integer',
        'effective_to_year' => 'integer',
        'duration_years' => 'integer',
        'enabled' => 'boolean',
    ];

    public function subjects()
    {
        return $this->hasMany(Subject::class)->orderBy('year_of_plan');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /** Planes vigentes en un anio dado. */
    public function scopeEffectiveIn($query, int $year)
    {
        return $query->where('effective_from_year', '<=', $year)
            ->where(function ($q) use ($year) {
                $q->whereNull('effective_to_year')
                    ->orWhere('effective_to_year', '>=', $year);
            });
    }
}
