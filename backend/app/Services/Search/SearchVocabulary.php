<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every distinct search token in the catalog (a few hundred words), used for
 * typo correction. Cached so autocomplete keystrokes don't re-read it; the
 * cache is cleared whenever a game is saved or deleted, and after seeding.
 */
class SearchVocabulary
{
    public const CACHE_KEY = 'search:vocabulary';

    /**
     * @return list<string>
     */
    public function words(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => DB::table('games')
            ->selectRaw('DISTINCT unnest(search_tokens) AS word')
            ->orderBy('word')
            ->pluck('word')
            ->all());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
