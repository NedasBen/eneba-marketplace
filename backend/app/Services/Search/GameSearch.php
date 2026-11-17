<?php

namespace App\Services\Search;

use App\Casts\PostgresTextArray;
use App\Enums\GamePlatform;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Typo-tolerant game search on top of pg_trgm.
 *
 * Every match gets a tier, and results sort by tier first, then trigram
 * scores within a tier:
 *
 *   4  exact name                          "eldn ring" -> "elden ring"
 *   3  name starts with the query          "red dead", "cyberpunk"
 *   2  query starts at a word boundary     "witcher" in "the witcher 3 ..."
 *      OR every query word is a token      "gta 5" (acronym + numeral)
 *   1  fuzzy only: word_similarity >= 0.6  (pg_trgm's default `<%` threshold)
 *
 * Within a tier: word_similarity (best-matching part of the name), then
 * similarity (whole-name overlap, so shorter/closer names win: "Fallout 4"
 * over "Fallout 76"), then sales. Queries shorter than 2 characters are
 * ignored; 2-3 characters never use the fuzzy tier or typo correction.
 */
class GameSearch
{
    public const MIN_QUERY_LENGTH = 2;

    public const SUGGESTION_LIMIT = 8;

    public function __construct(
        private readonly SearchVocabulary $vocabulary,
        private readonly QueryCorrector $corrector,
    ) {}

    /**
     * Normalize and correct a raw query; null when it is too short to search.
     */
    public function term(?string $query): ?SearchTerm
    {
        $normalized = GameSearchText::normalize((string) $query);

        if (strlen(str_replace(' ', '', $normalized)) < self::MIN_QUERY_LENGTH) {
            return null;
        }

        return new SearchTerm(
            original: trim((string) $query),
            normalized: $normalized,
            corrected: $this->corrector->correct($normalized, fn (): array => $this->vocabulary->words()),
        );
    }

    /**
     * Matching games with their tier and scores: game_id, tier, word_score, name_score.
     */
    public function scoredGames(SearchTerm $term): Builder
    {
        $query = $term->corrected;
        $wordPrefix = "% {$query}%";
        $tokens = PostgresTextArray::toLiteral($term->tokens());

        $tier = <<<'SQL'
            CASE
                WHEN games.search_name = ? THEN 4
                WHEN games.search_name LIKE ? THEN 3
                WHEN (' ' || games.search_name) LIKE ? OR games.search_tokens @> ?::text[] THEN 2
                ELSE 1
            END
            SQL;

        return DB::table('games')
            ->selectRaw(
                "games.id AS game_id, {$tier} AS tier, word_similarity(?, games.search_name) AS word_score, similarity(?, games.search_name) AS name_score",
                [$query, "{$query}%", $wordPrefix, $tokens, $query, $query],
            )
            ->where(function (Builder $match) use ($term, $query, $wordPrefix, $tokens): void {
                // The plain LIKE '%q%' lets the trigram index pre-filter rows.
                $match->whereRaw("(games.search_name LIKE ? AND (' ' || games.search_name) LIKE ?)", ["%{$query}%", $wordPrefix])
                    ->orWhereRaw('games.search_tokens @> ?::text[]', [$tokens]);

                if ($term->allowsFuzzyMatching()) {
                    $match->orWhereRaw('? <% games.search_name', [$query]);
                }
            });
    }

    /**
     * Top games for header autocomplete: one entry per game (not per
     * platform/region product), with its cheapest in-stock price and the
     * product to link to. A single query.
     *
     * @return list<array{name: string, slug: string, cover_image_url: string, price_from: array{amount_cents: int, currency: string}|null, platforms: list<string>, product_slug: string|null, in_stock: bool}>
     */
    public function suggest(SearchTerm $term, int $limit = self::SUGGESTION_LIMIT): array
    {
        $platformOrder = array_flip(array_column(GamePlatform::cases(), 'value'));

        return DB::query()
            ->fromSub($this->scoredGames($term), 'search')
            ->join('games', 'games.id', '=', 'search.game_id')
            ->leftJoin('products', 'products.game_id', '=', 'games.id')
            ->leftJoin('platforms', 'platforms.id', '=', 'products.platform_id')
            ->groupBy('games.id', 'search.tier', 'search.word_score', 'search.name_score')
            ->selectRaw(<<<'SQL'
                games.name, games.slug, games.cover_image_url,
                min(products.min_price_cents) AS price_from_cents,
                string_agg(DISTINCT platforms.slug, ',') FILTER (WHERE products.min_price_cents IS NOT NULL) AS platform_slugs,
                (array_agg(products.slug ORDER BY products.min_price_cents ASC NULLS LAST, products.sold_count DESC, products.id))[1] AS product_slug,
                coalesce(sum(products.sold_count), 0) AS sold
                SQL)
            ->orderByDesc('search.tier')
            ->orderByDesc('search.word_score')
            ->orderByDesc('search.name_score')
            ->orderByRaw('sold DESC')
            ->orderBy('games.id')
            ->limit($limit)
            ->get()
            ->map(function (object $game) use ($platformOrder): array {
                $platforms = $game->platform_slugs === null ? [] : explode(',', $game->platform_slugs);
                usort($platforms, fn (string $a, string $b): int => ($platformOrder[$a] ?? 99) <=> ($platformOrder[$b] ?? 99));

                return [
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'cover_image_url' => $game->cover_image_url,
                    'price_from' => Money::fromNullableCents($game->price_from_cents === null ? null : (int) $game->price_from_cents),
                    'platforms' => $platforms,
                    'product_slug' => $game->product_slug,
                    'in_stock' => $game->price_from_cents !== null,
                ];
            })
            ->all();
    }
}
