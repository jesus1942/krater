<?php

namespace Crater\Services\Site;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Textos y fotos del sitio publico, editables desde Configuracion > Sitio
 * publico. Un valor vacio vuelve al texto predeterminado de PublicSite.
 */
class SiteContent
{
    /** Campos de la institucion (solo administracion total). */
    const INSTITUTION_FIELDS = ['tagline', 'about', 'address', 'city', 'phone', 'whatsapp', 'email', 'instagram'];

    /** Campos de cada nivel (direccion del nivel o administracion total). */
    const LEVEL_FIELDS = ['tagline', 'summary', 'highlight_1_title', 'highlight_1_text', 'highlight_2_title', 'highlight_2_text',
        'highlight_3_title', 'highlight_3_text', 'phone', 'whatsapp', 'email', 'meeting_date', 'meeting_link'];

    const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

    protected static array $cache = [];

    public static function companyId(): ?int
    {
        if (! array_key_exists('company', static::$cache)) {
            // El sitio publico se muestra aunque la base no este lista (antes de migrar).
            try {
                static::$cache['company'] = (int) DB::table('companies')->orderBy('id')->value('id') ?: null;
            } catch (\Throwable $e) {
                static::$cache['company'] = null;
            }
        }

        return static::$cache['company'];
    }

    protected function safely(callable $read): array
    {
        try {
            return $read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function all(?int $companyId = null): array
    {
        $companyId = $companyId ?? static::companyId();
        if (! $companyId) {
            return [];
        }

        return static::$cache['values'][$companyId] ??= $this->safely(fn () => DB::table('site_contents')->where('company_id', $companyId)
            ->whereNotNull('value')->where('value', '<>', '')->pluck('value', 'key')->all());
    }

    public function images(?int $companyId = null): array
    {
        $companyId = $companyId ?? static::companyId();
        if (! $companyId) {
            return [];
        }

        return static::$cache['images'][$companyId] ??= $this->safely(fn () => DB::table('site_images')->where('company_id', $companyId)
            ->get(['key', 'updated_at', 'alt'])->keyBy('key')
            ->map(fn ($i) => ['url' => '/sitio/imagen/'.$i->key.'?v='.strtotime((string) $i->updated_at), 'alt' => $i->alt])->all());
    }

    public function save(int $companyId, string $prefix, array $fields, array $values, int $userId): void
    {
        DB::transaction(function () use ($companyId, $prefix, $fields, $values, $userId) {
            foreach ($fields as $field) {
                if (! array_key_exists($field, $values)) {
                    continue;
                }
                $value = $values[$field] === null ? null : trim((string) $values[$field]);
                DB::table('site_contents')->updateOrInsert(['company_id' => $companyId, 'key' => $prefix.$field],
                    ['value' => $value === '' ? null : $value, 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]);
            }
        });
        static::flush();
    }

    public function putImage(int $companyId, string $key, string $path, string $mime, ?string $alt, int $userId): void
    {
        if (! isset(self::IMAGE_TYPES[$mime])) {
            throw ValidationException::withMessages(['image' => 'La foto tiene que ser JPG, PNG o WebP.']);
        }
        $size = filesize($path);
        if ($size > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages(['image' => 'La foto supera los 4 MB: achicala antes de subirla.']);
        }
        DB::table('site_images')->updateOrInsert(['company_id' => $companyId, 'key' => $key], [
            'mime' => $mime, 'size' => $size, 'data' => base64_encode(file_get_contents($path)),
            'alt' => $alt, 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now(),
        ]);
        static::flush();
    }

    public function deleteImage(int $companyId, string $key): void
    {
        DB::table('site_images')->where('company_id', $companyId)->where('key', $key)->delete();
        static::flush();
    }

    public static function flush(): void
    {
        static::$cache = [];
    }
}
