<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'guardian_id',
        'first_name',
        'last_name',
        'dni',
        'birth_date',
        'level',
        'grade',
        'division',
        'school_year',
        'status',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'school_year' => 'integer',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return trim($this->last_name.', '.$this->first_name, ' ,');
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
