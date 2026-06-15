<?php

use Illuminate\Support\Facades\Route;
use Modules\Pharmacy\Http\Controllers\PrescriptionSlipController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('pharmacy/prescription-slip/combined', [PrescriptionSlipController::class, 'showCombined'])
        ->name('pharmacy.prescription-slip.combined');
    Route::get('pharmacy/prescription-slip/{requestItem}', [PrescriptionSlipController::class, 'show'])
        ->name('pharmacy.prescription-slip');
});
