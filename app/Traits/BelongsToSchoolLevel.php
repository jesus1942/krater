<?php

namespace Crater\Traits;

use Crater\Models\SchoolLevel;

trait BelongsToSchoolLevel
{
    protected static function bootBelongsToSchoolLevel()
    {
        static::creating(function ($model) {
            $levelId = request()->header('school-level');
            if (! $model->school_level_id && $levelId) {
                $isValid = SchoolLevel::whereKey($levelId)
                    ->where('company_id', request()->header('company'))
                    ->where('enabled', true)
                    ->exists();

                abort_unless($isValid, 403, 'El nivel seleccionado no pertenece a esta institución.');
                $model->school_level_id = $levelId;
            }
        });

        static::addGlobalScope('school_level', function ($query) {
            $levelId = request()->header('school-level');
            if ($levelId) {
                $isValid = SchoolLevel::whereKey($levelId)
                    ->where('company_id', request()->header('company'))
                    ->where('enabled', true)
                    ->exists();

                $query->where(
                    $query->getModel()->getTable().'.school_level_id',
                    $isValid ? $levelId : -1
                );
            }
        });
    }

    public function schoolLevel()
    {
        return $this->belongsTo(SchoolLevel::class);
    }
}
