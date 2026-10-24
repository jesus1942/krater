<?php

namespace Crater\Http\Controllers\V1\Audit;

use Crater\Http\Controllers\Controller;
use Crater\Models\AuditLog;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

class AuditLogsController extends Controller
{
    protected $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    public function index(Request $request)
    {
        $request->validate([
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $base = $this->scopedQuery($request);
        $summaryBase = clone $base;

        $logs = $base
            ->with(['user:id,name,email', 'company:id,name', 'schoolLevel:id,name'])
            ->when($request->severity, fn ($q, $v) => $q->where('severity', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->action, fn ($q, $v) => $q->where('action', 'like', "%{$v}%"))
            ->when($request->desde, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->hasta, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->paginate($request->por_pagina ?: 50);

        $payload = $logs->toArray();
        $payload['audit_summary'] = [
            'total' => (clone $summaryBase)->count(),
            'ultimos_7_dias' => (clone $summaryBase)->where('created_at', '>=', now()->subDays(7))->count(),
            'criticos_7_dias' => (clone $summaryBase)
                ->where('severity', AuditLog::SEVERITY_CRITICAL)
                ->where('created_at', '>=', now()->subDays(7))->count(),
            'altos_7_dias' => (clone $summaryBase)
                ->where('severity', AuditLog::SEVERITY_HIGH)
                ->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return response()->json($payload);
    }

    protected function scopedQuery(Request $request)
    {
        $query = AuditLog::query();
        $user = $request->user();

        // La administracion total conserva vista transversal completa.
        if ($this->access->isTotalAdmin($user)) {
            return $query;
        }

        $query->whereCompany(TenantContext::companyId());

        // Un rol limitado a un nivel ve SOLO ese nivel. Los eventos
        // institucionales (school_level_id NULL) quedan reservados a
        // quien tenga alcance global dentro de la empresa.
        if (TenantContext::schoolLevelId()) {
            $query->where('school_level_id', TenantContext::schoolLevelId());
        }

        return $query;
    }
}
