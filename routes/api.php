<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Services\ApiRouteRegistrar;
use Modules\Pharmacy\Http\Controllers\Api\DispenseController;
use Modules\Pharmacy\Http\Controllers\Api\DrugController;
use Modules\Pharmacy\Http\Controllers\Api\MedicationController;
use Modules\Pharmacy\Http\Controllers\Api\StockItemController;

ApiRouteRegistrar::register(
    scope: 'pharmacy',
    routes: fn () => Route::apiResource('drugs', DrugController::class)->only(['index', 'show']),
);

ApiRouteRegistrar::register(
    scope: 'pharmacy',
    routes: fn () => Route::apiResource('dispenses', DispenseController::class)->only(['index', 'show', 'update']),
);

ApiRouteRegistrar::register(
    scope: 'pharmacy',
    routes: function (): void {
        MedicationController::routes('medications');
        StockItemController::routes('pharmacy-stock', ['index', 'show']);
    },
);
