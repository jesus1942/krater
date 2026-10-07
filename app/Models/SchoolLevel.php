<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolLevel extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'company_id', 'code'];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
}
