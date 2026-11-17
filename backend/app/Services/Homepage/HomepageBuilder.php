<?php

namespace App\Services\Homepage;

use App\Models\HomepageBlock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Assembles GET /api/v1/homepage: active blocks in position order, each
 * resolved by its provider in isolation.
 *
 * Every provider runs in its own transaction with `SET LOCAL
 * statement_timeout`, so a slow query is cancelled by Postgres after the
 * configured time (and the setting ends with the transaction, which keeps
 * it safe behind Neon's transaction-mode pooler). A provider that fails,
 * times out or has no class is skipped and logged; the rest of the page is
 * still served. Complete responses are cached for `homepage.cache_ttl`,
 * degraded ones only for `homepage.degraded_cache_ttl`.
 */
class HomepageBuilder
{
    public const CACHE_KEY = 'homepage:v1';

    /** Postgres SQLSTATE for a query cancelled by statement_timeout. */
    private const QUERY_CANCELED = '57014';

    public function __construct(private readonly HomepageBlockRegistry $registry) {}

    /**
     * @return list<array{id: int, type: string, layout: string, title: string, data: array<string, mixed>}>
     */
    public function blocks(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        ['blocks' => $blocks, 'degraded' => $degraded] = $this->build();

        Cache::put(self::CACHE_KEY, $blocks, (int) config($degraded ? 'homepage.degraded_cache_ttl' : 'homepage.cache_ttl'));

        return $blocks;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{blocks: list<array<string, mixed>>, degraded: bool}
     */
    private function build(): array
    {
        $blocks = [];
        $degraded = false;

        $rows = HomepageBlock::query()->where('is_active', true)->orderBy('position')->orderBy('id')->get();

        foreach ($rows as $row) {
            $provider = $this->registry->for($row->type);

            if ($provider === null) {
                Log::warning('Homepage block skipped: no provider for its type.', ['block_id' => $row->id, 'type' => $row->type]);

                continue;
            }

            try {
                $data = $this->runWithTimeout($provider, $row);
            } catch (Throwable $exception) {
                $degraded = true;
                $timedOut = $exception instanceof QueryException && $exception->getCode() === self::QUERY_CANCELED;

                Log::warning($timedOut ? 'Homepage block skipped: timed out.' : 'Homepage block skipped: provider failed.', [
                    'block_id' => $row->id,
                    'type' => $row->type,
                    'timeout_ms' => config('homepage.block_timeout_ms'),
                    'exception' => $exception,
                ]);

                continue;
            }

            if ($data === null || $data === []) {
                continue;
            }

            $blocks[] = [
                'id' => $row->id,
                'type' => $row->type,
                'layout' => $provider::layout(),
                'title' => $row->title,
                'data' => $data,
            ];
        }

        return ['blocks' => $blocks, 'degraded' => $degraded];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runWithTimeout(HomepageBlockProvider $provider, HomepageBlock $block): ?array
    {
        $timeoutMs = max(1, (int) config('homepage.block_timeout_ms'));

        return DB::transaction(function () use ($provider, $block, $timeoutMs): ?array {
            DB::statement("SET LOCAL statement_timeout = {$timeoutMs}");

            return $provider->data($block);
        });
    }
}
