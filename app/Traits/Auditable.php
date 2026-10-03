<?php

namespace Crater\Traits;

use Crater\Models\AuditLog;
use Crater\Services\Audit\Auditor;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            $model->registrarEnBitacora('created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $cambios = $model->getChanges();
            unset($cambios['updated_at']);
            if (empty($cambios)) {
                return;
            }

            $anteriores = [];
            foreach (array_keys($cambios) as $campo) {
                $anteriores[$campo] = $model->getOriginal($campo);
            }
            $model->registrarEnBitacora('updated', $anteriores, $cambios);
        });

        static::deleted(function ($model) {
            $model->registrarEnBitacora('deleted', $model->getOriginal(), []);
        });
    }

    protected function registrarEnBitacora(string $evento, array $anteriores, array $nuevos): void
    {
        $nombre = strtolower(class_basename($this));
        $user = app()->bound('auth') ? Auth::user() : null;

        app(Auditor::class)->record(
            $nombre.'.'.$evento,
            $user,
            [
                'type' => static::class,
                'id' => $this->getKey(),
                'company_id' => $this->getAttribute('company_id'),
                'school_level_id' => $this->getAttribute('school_level_id'),
            ],
            $anteriores,
            $nuevos,
            $this->auditSeverity ?? AuditLog::SEVERITY_MEDIUM
        );
    }
}
