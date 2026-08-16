<?php

namespace Crater\Models;

use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Division: "3.er anio A" de un ciclo concreto.
 */
class Division extends Model
{
    use BelongsToSchoolLevel;

    protected $guarded = ['id'];

    protected $casts = [
        'capacity' => 'integer',
        'enabled' => 'boolean',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** Preceptor o tutor a cargo del grupo. */
    public function headTeacher()
    {
        return $this->belongsTo(User::class, 'head_teacher_id');
    }

    public function courseSections()
    {
        return $this->hasMany(CourseSection::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeForYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /** Nombre completo para mostrar: "3.er anio A". */
    public function getFullNameAttribute(): string
    {
        return trim(($this->gradeLevel->name ?? '').' '.$this->name);
    }

    public function hasCapacity(): bool
    {
        if (! $this->capacity) {
            return true;
        }

        return $this->enrollments()->where('status', 'active')->count() < $this->capacity;
    }
}
