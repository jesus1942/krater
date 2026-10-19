<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

/** Asistencia diaria de una persona del plantel. */
class StaffAttendance extends Model
{
    const STATUSES = ['present', 'absent', 'late', 'leave'];

    protected $table = 'staff_attendance';

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function member()
    {
        return $this->belongsTo(StaffMember::class, 'staff_member_id');
    }
}
