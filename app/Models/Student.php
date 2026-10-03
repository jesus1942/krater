<?php

namespace Crater\Models;

use Crater\Traits\Auditable;
use Crater\Traits\BelongsToSchoolLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use Auditable;
    use HasFactory;
    use BelongsToSchoolLevel;

    protected $fillable = [
        'company_id',
        'school_level_id',
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

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function scopeAccessibleTo($query, User $actor)
    {
        $access = app(\Crater\Services\Access\AccessManager::class);
        if ($access->hasLevelWideScope($actor, \Crater\Support\TenantContext::schoolLevelId(), 'students.view_basic')) {
            return $query;
        }

        return $query->whereHas('enrollments', function ($enrollments) use ($access, $actor) {
            $enrollments->active()->whereIn('division_id', $access->scopedDivisionIds($actor, 'students.view_basic', \Crater\Support\TenantContext::schoolLevelId()));
        });
    }

    public function familyMembers()
    {
        return $this->belongsToMany(FamilyMember::class, 'student_family_members')
            ->withPivot([
                'relationship',
                'is_responsible',
                'is_financial_responsible',
                'is_primary_contact',
            ])
            ->withTimestamps();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
