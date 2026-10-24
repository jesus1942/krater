<?php

use Crater\Http\Controllers\V1\Customer\CustomerStatsController;
use Crater\Http\Controllers\V1\Estimate\ChangeEstimateStatusController;
use Crater\Http\Controllers\V1\Estimate\ConvertEstimateController;
use Crater\Http\Controllers\V1\Estimate\SendEstimateController;
use Crater\Http\Controllers\V1\Expense\ShowReceiptController;
use Crater\Http\Controllers\V1\Expense\UploadReceiptController;
use Crater\Http\Controllers\V1\Invoice\ChangeInvoiceStatusController;
use Crater\Http\Controllers\V1\Invoice\CloneInvoiceController;
use Crater\Http\Controllers\V1\Invoice\SendInvoiceController;
use Crater\Http\Controllers\V1\Payment\SendPaymentController;
use Tests\TestCase;

uses(TestCase::class);

test('economic auxiliary controllers initialize tenant before route binding', function () {
    $controllers = [
        CustomerStatsController::class,
        ChangeEstimateStatusController::class,
        ConvertEstimateController::class,
        SendEstimateController::class,
        ShowReceiptController::class,
        UploadReceiptController::class,
        ChangeInvoiceStatusController::class,
        CloneInvoiceController::class,
        SendInvoiceController::class,
        SendPaymentController::class,
    ];

    foreach ($controllers as $controller) {
        $middleware = collect(app($controller)->getMiddleware())
            ->pluck('middleware')
            ->all();

        expect($middleware)->toContain('tenant', $controller.' must initialize tenant context');
    }
});
