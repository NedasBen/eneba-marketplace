<?php

return [

    /*
     * Seconds the assembled homepage is cached for. A response missing a block
     * (failed or timed out) is cached only briefly, so one hiccup doesn't hide
     * the block for the full TTL.
     */
    'cache_ttl' => (int) env('HOMEPAGE_CACHE_TTL', 60),
    'degraded_cache_ttl' => (int) env('HOMEPAGE_DEGRADED_CACHE_TTL', 5),

    /*
     * Postgres statement_timeout per block provider, in milliseconds. A slower
     * block is skipped instead of holding up the whole page.
     */
    'block_timeout_ms' => (int) env('HOMEPAGE_BLOCK_TIMEOUT_MS', 500),

];
