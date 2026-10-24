<?php

use Crater\Http\Controllers\V1\Dashboard\DashboardChartController;
use Crater\Http\Controllers\V1\Dashboard\DashboardController;
use Tests\TestCase;

uses(TestCase::class);

test('dashboard controllers initialize tenant context', function () {
    foreach ([DashboardController::class, DashboardChartController::class] as $controller) {
        $middleware = collect(app($controller)->getMiddleware())
            ->pluck('middleware')
            ->all();

        expect($middleware)->toContain('tenant', $controller.' must initialize tenant context');
    }
});
