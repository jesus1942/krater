<?php

namespace Crater\Models;

use Crater\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila E1: una persona dicta una materia en un curso (seccion), con horas y
 * periodo. Si tiene acceso, role_user_id es su rol docente sobre esa seccion:
 * revocar la asignacion revoca ese rol en el momento.
 */
class TeachingAssignment extends Model
{
    use Auditable;

    protected $fillable = [
        'company_id', 'school_level_id', 'staff_member_id', 'course_section_id',
        'weekly_hours', 'starts_on', 'ends_on', 'role_user_id',
        'revoked_at', 'revoked_by', 'created_by',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'revoked_at' => 'datetime',
        'weekly_hours' => 'decimal:2',
    ];

    public function staffMember()
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
