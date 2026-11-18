<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogFilterController;
use App\Http\Controllers\Api\V1\HomepageController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SearchSuggestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('ping', PingController::class)->name('ping');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        // Per-IP ceiling on top of LoginRequest's per-email+IP lockout.
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product:slug}', [ProductController::class, 'show'])
        ->missing(fn () => response()->json(['message' => 'Product not found.'], 404))
        ->name('products.show');
    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('catalog/filters', CatalogFilterController::class)->name('catalog.filters');
    Route::get('homepage', HomepageController::class)->name('homepage');
    Route::get('search/suggest', SearchSuggestController::class)
        ->middleware('throttle:search')
        ->name('search.suggest');
});
