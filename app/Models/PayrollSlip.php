<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSlip extends Model
{
    protected $guarded = ['id'];

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function schoolLevel()
    {
        return $this->belongsTo(SchoolLevel::class);
    }

    public function payments()
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function activePayments()
    {
        return $this->hasMany(PayrollPayment::class)->whereNull('reversed_at');
    }
}
