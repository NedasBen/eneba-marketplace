<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PingController extends Controller
{
    /**
     * Report API liveness together with a real database round-trip.
     *
     * Unlike Laravel's `/up` health route (which Render uses and which must stay
     * green while Neon wakes up), this endpoint proves the full stack works.
     * It is public, so it reports only liveness and latency: no environment
     * name, versions or database details.
     */
    public function __invoke(): JsonResponse
    {
        $startedAt = hrtime(true);

        try {
            DB::scalar('select 1');
        } catch (QueryException $exception) {
            Log::error('Ping database check failed', ['exception' => $exception->getMessage()]);

            return response()->json([
                'status' => 'degraded',
                'database' => ['connected' => false],
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'database' => [
                'connected' => true,
                'latency_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 1),
            ],
        ]);
    }
}
