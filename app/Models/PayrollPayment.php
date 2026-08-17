<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'paid_at' => 'date:Y-m-d',
        'reversed_at' => 'datetime',
    ];

    public function slip()
    {
        return $this->belongsTo(PayrollSlip::class, 'payroll_slip_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
