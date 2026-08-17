<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    protected static function booted()
    {
        static::updating(function () {
            throw new RuntimeException('La bitacora de auditoria es append-only: no se modifica.');
        });

        static::deleting(function () {
            throw new RuntimeException('La bitacora de auditoria es append-only: no se borra.');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function schoolLevel()
    {
        return $this->belongsTo(SchoolLevel::class);
    }

    public function scopeWhereCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
