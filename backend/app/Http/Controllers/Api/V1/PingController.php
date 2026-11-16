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
     */
    public function __invoke(): JsonResponse
    {
        $startedAt = hrtime(true);

        try {
            $databaseTime = DB::scalar('select now()');
        } catch (QueryException $exception) {
            Log::error('Ping database check failed', ['exception' => $exception->getMessage()]);

            return response()->json([
                'status' => 'degraded',
                'database' => ['connected' => false],
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'environment' => app()->environment(),
            'database' => [
                'connected' => true,
                'server_time' => $databaseTime,
                'latency_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 1),
            ],
        ]);
    }
}
