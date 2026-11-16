<?php

use Illuminate\Support\Facades\Route;

/*
 * This app is a JSON API consumed by the React frontend; the root URL
 * just identifies the service instead of rendering a Blade view.
 */
Route::get('/', fn () => response()->json([
    'service' => 'eneba-marketplace-api',
    'api' => url('/api/v1'),
    'health' => url('/up'),
]));
