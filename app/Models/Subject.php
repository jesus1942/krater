<?php

namespace Crater\Models;

use Crater\Traits\Auditable;
use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Espacio curricular. La materia como definicion del plan, no como dictado.
 */
class Subject extends Model
{
    use Auditable;
    use BelongsToSchoolLevel;

    const DURATION_ANNUAL = 'annual';
    const DURATION_FIRST_SEMESTER = 'first_semester';
    const DURATION_SECOND_SEMESTER = 'second_semester';
    const DURATION_MODULAR = 'modular';

    protected $guarded = ['id'];

    protected $casts = [
        'year_of_plan' => 'integer',
        'weekly_hours' => 'integer',
        'total_hours' => 'integer',
        'counts_for_promotion' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function studyPlan()
    {
        return $this->belongsTo(StudyPlan::class);
    }

    public function courseSections()
    {
        return $this->hasMany(CourseSection::class);
    }

    /** Materias que hay que tener antes de esta. */
    public function prerequisites()
    {
        return $this->belongsToMany(
            Subject::class,
            'subject_prerequisites',
            'subject_id',
            'required_subject_id'
        )->withPivot(['scope', 'requirement']);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
