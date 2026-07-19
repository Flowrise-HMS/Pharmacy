<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Services\ApiRouteRegistrar;
use Modules\Pharmacy\Http\Controllers\Api\DispenseController;
use Modules\Pharmacy\Http\Controllers\Api\DrugController;

ApiRouteRegistrar::register(
    namePrefix: 'drugs',
    routes: fn () => Route::apiResource('drugs', DrugController::class)->only(['index', 'show']),
);

ApiRouteRegistrar::register(
    namePrefix: 'dispenses',
    routes: fn () => Route::apiResource('dispenses', DispenseController::class)->only(['index', 'show', 'update']),
);
