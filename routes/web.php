<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    // Pharmacy module web routes are handled by Filament resources/pages.
});
