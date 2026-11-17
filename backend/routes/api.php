<?php

use App\Http\Controllers\Api\V1\CatalogFilterController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SearchSuggestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('ping', PingController::class)->name('ping');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('catalog/filters', CatalogFilterController::class)->name('catalog.filters');
    Route::get('search/suggest', SearchSuggestController::class)
        ->middleware('throttle:search')
        ->name('search.suggest');
});
