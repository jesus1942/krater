<?php

namespace Crater\Http\Controllers\V1\Data;

use Crater\Http\Controllers\Controller;
use Crater\Services\Data\LevelReconciliationService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DataReconciliationController extends Controller
{
    protected $service;

    public function __construct(LevelReconciliationService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->ensureWholeInstitution($request);
        $companyId = (int) $request->header('company');

        return response()->json([
            'groups' => $this->service->listOrphans($companyId),
            'levels' => $this->service->levels($companyId),
            'models' => $this->service->modelCatalog(),
        ]);
    }

    public function preview(Request $request)
    {
        $this->ensureWholeInstitution($request);
        $data = $this->validatedPayload($request, false);
        $companyId = (int) $request->header('company');

        try {
            $preview = $this->service->preview(
                $data['model'],
                $data['ids'],
                (int) $data['school_level_id'],
                $companyId
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['preview' => $preview]);
    }

    public function apply(Request $request)
    {
        $this->ensureWholeInstitution($request);
        $data = $this->validatedPayload($request, true);
        $companyId = (int) $request->header('company');

        try {
            $result = $this->service->apply(
                $data['model'],
                $data['ids'],
                (int) $data['school_level_id'],
                $companyId,
                $request->user(),
                $data['reason']
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'reconciled' => $result,
        ]);
    }

    protected function validatedPayload(Request $request, bool $withReason): array
    {
        $rules = [
            'model' => ['required', 'string', 'max:100'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'school_level_id' => ['required', 'integer'],
        ];

        if ($withReason) {
            $rules['reason'] = ['required', 'string', 'max:500'];
        }

        return $request->validate($rules);
    }

    protected function ensureWholeInstitution(Request $request): void
    {
        if ($request->header('school-level') !== null && $request->header('school-level') !== '') {
            abort(response()->json([
                'message' => 'La reconciliacion solo esta disponible en Toda la institucion.',
            ], 409));
        }
    }
}
