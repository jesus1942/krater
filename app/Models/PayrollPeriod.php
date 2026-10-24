<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected $guarded = ['id'];

    public function schoolLevel()
    {
        return $this->belongsTo(SchoolLevel::class);
    }

    public function slips()
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
