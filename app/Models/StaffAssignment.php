<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;
use Crater\Traits\Auditable;

class StaffAssignment extends Model
{
    use Auditable;
    protected $fillable = [
        'company_id',
        'staff_member_id',
        'school_level_id',
        'position_code',
        'position_title',
        'function_category',
        'start_date',
        'end_date',
        'active',
        'weekly_hours',
        'notes',
        'role_id',
        'division_id',
        'role_user_id',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'active' => 'boolean',
        'weekly_hours' => 'decimal:2',
    ];

    public function staffMember()
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function schoolLevel()
    {
        return $this->belongsTo(SchoolLevel::class);
    }
}
