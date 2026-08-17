<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMember extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'document_type',
        'document_number',
        'document_number_normalized',
        'first_name',
        'last_name',
        'email',
        'phone',
        'staff_category',
        'employment_status',
        'hire_date',
        'termination_date',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date:Y-m-d',
        'termination_date' => 'date:Y-m-d',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return trim($this->last_name.', '.$this->first_name, ' ,');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(StaffAssignment::class);
    }
}
