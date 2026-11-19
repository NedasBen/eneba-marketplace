<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS at its load balancer and forwards X-Forwarded-* headers.
        // Trusting every proxy is safe only because the app is reachable solely through
        // Render's proxy, which rewrites X-Forwarded-For to the real client IP (checked in
        // production: spoofed X-Forwarded-For headers did not escape the login lockout).
        // The per-IP rate limits depend on this. Behind any other host, list its proxy
        // ranges here instead of '*', or clients could pick their own IP.
        $middleware->trustProxies(at: '*');

        // No server-rendered login page exists: never try to redirect API guests
        // (route('login') would throw and turn a 401 into a 500).
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API routes always answer in JSON (e.g. 401 {"message":"Unauthenticated."}),
        // even without an Accept header, instead of redirecting to a web login page.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());

        // Card data is never stored or logged; also keep it out of flashed old input.
        $exceptions->dontFlash(['card_number', 'cvc']);
    })->create();
