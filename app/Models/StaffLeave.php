<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

/** Licencia del personal: rango de fechas con su tipo. */
class StaffLeave extends Model
{
    const TYPES = [
        'illness' => 'Enfermedad', 'family' => 'Atención de familiar', 'training' => 'Capacitación',
        'study' => 'Examen o estudio', 'union' => 'Gremial', 'personal' => 'Razones particulares', 'other' => 'Otra',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(StaffMember::class, 'staff_member_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopeCovering($query, string $date)
    {
        return $query->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }
}
