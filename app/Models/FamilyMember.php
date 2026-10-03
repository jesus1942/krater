<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'dni',
        'email',
        'phone',
        'notes',
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_family_members')
            ->withPivot([
                'relationship',
                'is_responsible',
                'is_financial_responsible',
                'is_primary_contact',
            ])
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
