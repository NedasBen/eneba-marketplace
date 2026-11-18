<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogFilterController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\HomepageController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SearchSuggestController;
use App\Http\Middleware\SweepExpiredReservations;
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

    Route::prefix('cart')->name('cart.')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/', [CartController::class, 'index'])->name('show');
        Route::post('items', [CartController::class, 'store'])->name('items.store');
        Route::patch('items/{cartItem}', [CartController::class, 'update'])->whereNumber('cartItem')->name('items.update');
        Route::delete('items/{cartItem}', [CartController::class, 'destroy'])->whereNumber('cartItem')->name('items.destroy');
        Route::post('merge', [CartController::class, 'merge'])->name('merge');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{reference}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{reference}/pay', [OrderController::class, 'pay'])->middleware('throttle:payments')->name('orders.pay');
    });

    // Catalog reads also tidy up expired reservations (throttled, after the response).
    Route::middleware(SweepExpiredReservations::class)->group(function (): void {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product:slug}', [ProductController::class, 'show'])
            ->missing(fn () => response()->json(['message' => 'Product not found.'], 404))
            ->name('products.show');
        Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
        Route::get('catalog/filters', CatalogFilterController::class)->name('catalog.filters');
        Route::get('homepage', HomepageController::class)->name('homepage');
    });
    Route::get('search/suggest', SearchSuggestController::class)
        ->middleware('throttle:search')
        ->name('search.suggest');
});
