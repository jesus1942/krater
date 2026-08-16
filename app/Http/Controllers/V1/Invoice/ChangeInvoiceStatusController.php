<?php

namespace Crater\Http\Controllers\V1\Invoice;

use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Illuminate\Http\Request;

class ChangeInvoiceStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('tenant');
    }

    /**
    * Handle the incoming request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\JsonResponse
    */
    public function __invoke(Request $request, Invoice $invoice)
    {
        if ($request->status == Invoice::STATUS_SENT) {
            $invoice->status = Invoice::STATUS_SENT;
            $invoice->sent = true;
            $invoice->save();
        } elseif ($request->status == Invoice::STATUS_COMPLETED) {
            $invoice->status = Invoice::STATUS_COMPLETED;
            $invoice->paid_status = Invoice::STATUS_PAID;
            $invoice->due_amount = 0;
            $invoice->save();
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
