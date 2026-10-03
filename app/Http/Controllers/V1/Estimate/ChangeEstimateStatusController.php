<?php

namespace Crater\Http\Controllers\V1\Estimate;

use Crater\Http\Controllers\Controller;
use Crater\Models\Estimate;
use Illuminate\Http\Request;

class ChangeEstimateStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('tenant');
    }

    /**
    * Handle the incoming request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  Estimate $estimate
    * @return \Illuminate\Http\Response
    */
    public function __invoke(Request $request, Estimate $estimate)
    {
        $estimate->update($request->only('status'));

        return response()->json([
            'success' => true,
        ]);
    }
}
