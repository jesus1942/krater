<?php

namespace Crater\Models;

use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Seccion de materia: el dictado concreto de una materia en una division.
 *
 * Es el objeto que se espeja como curso en Moodle y el que agrupa las clases en
 * vivo. Tambien es la unidad de alcance del rol docente: un docente ve las
 * secciones que tiene en `user_scopes`, y ninguna otra.
 */
class CourseSection extends Model
{
    use BelongsToSchoolLevel;

    protected $guarded = ['id'];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'course_section_teacher')
            ->withPivot(['role', 'starts_on', 'ends_on'])
            ->withTimestamps();
    }

    public function sectionEnrollments()
    {
        return $this->hasMany(SectionEnrollment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Secciones a cargo de un usuario, segun su alcance asignado.
     *
     * Consulta `user_scopes`, no `course_section_teacher`: la primera es lo que
     * el resolutor de permisos evalua, la segunda describe la realidad
     * administrativa. Mantenerlas sincronizadas es responsabilidad del servicio
     * que asigna docentes.
     */
    public function scopeScopedToUser($query, $userId)
    {
        return $query->whereIn('id', function ($sub) use ($userId) {
            $sub->select('scope_id')
                ->from('user_scopes')
                ->where('user_id', $userId)
                ->where('scope_type', 'course_section');
        });
    }
}
