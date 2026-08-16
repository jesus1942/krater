<?php

namespace Crater\Services\Audit;

use Crater\Models\AuditLog;
use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

class Auditor
{
    const CAMPOS_EXCLUIDOS = [
        'password', 'remember_token', 'credentials', 'value',
        'signature_data', 'attendee_secret', 'moderator_secret',
        'teacher_comment', 'justification', 'notes', 'override_reason',
        'equivalence_note',
    ];

    public function record(
        string $action,
        ?User $user = null,
        array $sobre = [],
        array $anteriores = [],
        array $nuevos = [],
        string $severity = AuditLog::SEVERITY_LOW
    ): void {
        try {
            $companyId = array_key_exists('company_id', $sobre)
                ? $sobre['company_id']
                : (TenantContext::companyId() ?: optional($user)->company_id);
            $schoolLevelId = array_key_exists('school_level_id', $sobre)
                ? $sobre['school_level_id']
                : TenantContext::schoolLevelId();

            $request = app()->bound('request') ? request() : null;

            AuditLog::create([
                'company_id' => $companyId,
                'school_level_id' => $schoolLevelId,
                'user_id' => optional($user)->id,
                'action' => $action,
                'auditable_type' => $sobre['type'] ?? null,
                'auditable_id' => $sobre['id'] ?? null,
                'old_values' => $this->limpiar($anteriores) ?: null,
                'new_values' => $this->limpiar($nuevos) ?: null,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
                'severity' => $severity,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('No se pudo escribir en la bitacora de auditoria', [
                'action' => $action,
                'user_id' => optional($user)->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordPermissionUse(string $permission, User $user, string $ruta, string $metodo): void
    {
        $this->record(
            'permission.'.$permission,
            $user,
            [],
            [],
            ['ruta' => $metodo.' '.$ruta],
            $this->severidadDe($permission)
        );
    }

    protected function limpiar(array $valores): array
    {
        $limpio = [];
        foreach ($valores as $clave => $valor) {
            if (in_array($clave, self::CAMPOS_EXCLUIDOS, true)) {
                $limpio[$clave] = '[omitido]';
                continue;
            }
            if (is_string($valor) && mb_strlen($valor) > 500) {
                $limpio[$clave] = mb_substr($valor, 0, 500).'… [recortado]';
                continue;
            }
            $limpio[$clave] = $valor;
        }
        return $limpio;
    }

    protected function severidadDe(string $permission): string
    {
        $criticos = [
            'system.secrets.manage', 'system.secrets.view_masked',
            'system.role.manage', 'promotion.revert',
            'promotion.execute', 'academic.year.close',
        ];
        if (in_array($permission, $criticos, true)) {
            return AuditLog::SEVERITY_CRITICAL;
        }

        $altos = [
            'students.view_sensitive', 'system.role.assign',
            'documents.annul', 'documents.sign',
            'grading.term.reopen', 'academic.enrollment.transfer',
        ];

        return in_array($permission, $altos, true)
            ? AuditLog::SEVERITY_HIGH
            : AuditLog::SEVERITY_MEDIUM;
    }
}
