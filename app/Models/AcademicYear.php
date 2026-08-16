<?php

namespace Crater\Models;

use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Ciclo lectivo. La unidad de corte de toda la vida academica: sin ciclo no hay
 * matricula, ni nota, ni promocion.
 */
class AcademicYear extends Model
{
    use BelongsToSchoolLevel;

    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSING = 'closing';
    const STATUS_CLOSED = 'closed';

    protected $guarded = ['id'];

    protected $casts = [
        'year' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'closed_at' => 'datetime',
    ];

    public function terms()
    {
        return $this->hasMany(AcademicTerm::class)->orderBy('position');
    }

    public function divisions()
    {
        return $this->hasMany(Division::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeWhereCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Un ciclo cerrado es historia: no se le tocan notas ni matriculas.
     * Los servicios consultan esto antes de escribir.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ACTIVE], true);
    }
}
