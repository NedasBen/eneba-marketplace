<?php

namespace App\Http\Middleware;

use App\Services\Checkout\ReleaseExpiredReservations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * On catalog reads, release expired reservations at most once a minute,
 * after the response has been sent (there's no scheduler on Render's free
 * tier). Only tidies stored state: stock is already correct without it.
 */
class SweepExpiredReservations
{
    public function __construct(private readonly ReleaseExpiredReservations $expiry) {}

    public function handle(Request $request, Closure $next): Response
    {
        defer(fn () => $this->expiry->runThrottled(), 'sweep-expired-reservations');

        return $next($request);
    }
}
