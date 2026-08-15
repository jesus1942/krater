<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Company extends Model implements HasMedia
{
    use InteractsWithMedia;

    use HasFactory;

    protected $fillable = ['name', 'logo', 'unique_hash'];

    protected $appends = ['logo', 'logo_path'];

    public function getLogoPathAttribute()
    {
        $logo = $this->getMedia('logo')->first();
        $disk = FileDisk::whereSetAsDefault(true)->first();
        $isSystem = $disk ? $disk->isSystem() : true;

        if ($logo) {
            if (! $isSystem) {
                return $logo->getFullUrl();
            }

            if (file_exists($logo->getPath())) {
                return $logo->getPath();
            }
        }

        return public_path('images/ena-logo.svg');
    }

    public function getLogoAttribute()
    {
        $logo = $this->getMedia('logo')->first();
        $disk = FileDisk::whereSetAsDefault(true)->first();
        $isSystem = $disk ? $disk->isSystem() : true;

        if ($logo) {
            if (! $isSystem || file_exists($logo->getPath())) {
                return $logo->getFullUrl();
            }
        }

        return asset('/images/ena-logo.svg');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function settings()
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function schoolLevels()
    {
        return $this->hasMany(SchoolLevel::class);
    }

    public function address()
    {
        return $this->hasOne(Address::class);
    }
}
