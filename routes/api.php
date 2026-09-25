<?php

use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\ListingSearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // "search" is declared first and the listing id is numeric-only, so the two cannot clash.
    Route::get('listings/search', ListingSearchController::class)->name('listings.search');

    Route::apiResource('listings', ListingController::class)->where(['listing' => '[0-9]+']);
});
