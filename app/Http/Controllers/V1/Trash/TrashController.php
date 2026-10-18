<?php

namespace Crater\Http\Controllers\V1\Trash;

use Crater\Http\Controllers\Controller;
use Crater\Services\Data\TrashBin;
use Illuminate\Http\Request;

/**
 * Papelera: solo total admin. Lista y restaura bajas logicas; el borrado
 * definitivo no se expone.
 */
class TrashController extends Controller
{
    public function __construct()
    {
        $this->middleware('tenant');
    }

    public function index(Request $request, string $type, TrashBin $trash)
    {
        $limit = min(max((int) $request->query('limit', 25), 1), 100);

        return response()->json(['records' => $trash->list($type, $limit)]);
    }

    public function restore(string $type, int $id, TrashBin $trash)
    {
        return response()->json(['success' => true, 'record' => $trash->restore($type, $id)]);
    }
}
