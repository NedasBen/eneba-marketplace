<?php

namespace App\Services\Catalog;

use App\Enums\ProductSort;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Region;
use App\Services\Search\GameSearch;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Builds store queries from CatalogFilters.
 *
 * Within a facet, options are OR-ed (PC or Xbox); across facets they are
 * AND-ed (PC and EUROPE). Prices filter and sort on the denormalised
 * products.min_price_cents ("from" price) maintained by ProductStockAggregator.
 */
class ProductCatalogQuery
{
    public function __construct(private readonly GameSearch $gameSearch) {}

    /**
     * Filtered and sorted products, ready to eager load and paginate.
     *
     * @return Builder<Product>
     */
    public function products(CatalogFilters $filters): Builder
    {
        $query = $this->filtered($filters)->select('products.*');

        match ($filters->sort === ProductSort::Relevance && $filters->search === null ? ProductSort::Popularity : $filters->sort) {
            // Search tier, then trigram scores (see GameSearch), then sales.
            ProductSort::Relevance => $query
                ->orderByDesc('search.tier')
                ->orderByDesc('search.word_score')
                ->orderByDesc('search.name_score')
                ->orderByDesc('products.sold_count'),
            ProductSort::Popularity => $query->orderByDesc('products.sold_count'),
            ProductSort::PriceAsc => $query->orderByRaw('products.min_price_cents ASC NULLS LAST'),
            ProductSort::PriceDesc => $query->orderByRaw('products.min_price_cents DESC NULLS LAST'),
            ProductSort::ReleaseDate => $query
                ->join('games', 'games.id', '=', 'products.game_id')
                ->orderByRaw('games.released_at DESC NULLS LAST'),
            ProductSort::Name => $query->orderBy('products.name'),
        };

        // Stable tie-breaker so equal values never shuffle between pages.
        return $query->orderBy('products.id');
    }

    /**
     * Everything the filter sidebar needs, straight from the database.
     *
     * Each facet's counts apply every active filter except that facet's own.
     *
     * @return array{
     *     platforms: list<array{slug: string, name: string, activation_service: string, count: int}>,
     *     regions: list<array{slug: string, name: string, count: int}>,
     *     genres: list<array{slug: string, name: string, count: int}>,
     *     price: array{min: array{amount_cents: int, currency: string}|null, max: array{amount_cents: int, currency: string}|null},
     *     sorts: list<array{value: string, label: string, requires_query: bool}>,
     *     default_sort: string,
     *     per_page: array{options: list<int>, default: int}
     * }
     */
    public function facets(CatalogFilters $filters): array
    {
        $platformCounts = $this->countsBy($filters->without('platform'), 'products.platform_id');
        $regionCounts = $this->countsBy($filters->without('region'), 'products.region_id');

        $genreCounts = $this->filtered($filters->without('genre'))
            ->join('game_genre', 'game_genre.game_id', '=', 'products.game_id')
            ->groupBy('game_genre.genre_id')
            ->toBase()
            ->selectRaw('game_genre.genre_id AS facet_id, count(DISTINCT products.id) AS product_count')
            ->pluck('product_count', 'facet_id')
            ->all();

        $priceRange = $this->filtered($filters->without('price'))
            ->toBase()
            ->selectRaw('min(products.min_price_cents) AS min_cents, max(products.min_price_cents) AS max_cents')
            ->first();

        return [
            'platforms' => Platform::query()->orderBy('id')->get(['id', 'slug', 'name', 'activation_service'])
                ->map(fn (Platform $platform): array => [
                    'slug' => $platform->slug,
                    'name' => $platform->name,
                    'activation_service' => $platform->activation_service,
                    'count' => (int) ($platformCounts[$platform->id] ?? 0),
                ])->all(),
            'regions' => Region::query()->orderBy('id')->get(['id', 'slug', 'name'])
                ->map(fn (Region $region): array => [
                    'slug' => $region->slug,
                    'name' => $region->name,
                    'count' => (int) ($regionCounts[$region->id] ?? 0),
                ])->all(),
            'genres' => Genre::query()->orderBy('name')->get(['id', 'slug', 'name'])
                ->map(fn (Genre $genre): array => [
                    'slug' => $genre->slug,
                    'name' => $genre->name,
                    'count' => (int) ($genreCounts[$genre->id] ?? 0),
                ])->all(),
            'price' => [
                'min' => Money::fromNullableCents($priceRange?->min_cents === null ? null : (int) $priceRange->min_cents),
                'max' => Money::fromNullableCents($priceRange?->max_cents === null ? null : (int) $priceRange->max_cents),
            ],
            'sorts' => array_map(
                fn (ProductSort $sort): array => ['value' => $sort->value, 'label' => $sort->label(), 'requires_query' => $sort->requiresQuery()],
                ProductSort::cases(),
            ),
            'default_sort' => ($filters->search === null ? ProductSort::Popularity : ProductSort::Relevance)->value,
            'per_page' => ['options' => CatalogFilters::PER_PAGE_OPTIONS, 'default' => CatalogFilters::DEFAULT_PER_PAGE],
        ];
    }

    /**
     * @return Builder<Product>
     */
    private function filtered(CatalogFilters $filters): Builder
    {
        $query = Product::query();

        if ($filters->search !== null) {
            // One row per matching game, so joining never duplicates products.
            $query->joinSub($this->gameSearch->scoredGames($filters->search), 'search', 'search.game_id', '=', 'products.game_id');
        }

        if ($filters->platformSlugs !== []) {
            $query->whereIn('products.platform_id', Platform::query()->select('id')->whereIn('slug', $filters->platformSlugs));
        }

        if ($filters->regionSlugs !== []) {
            $query->whereIn('products.region_id', Region::query()->select('id')->whereIn('slug', $filters->regionSlugs));
        }

        if ($filters->genreSlugs !== []) {
            // EXISTS, not JOIN: a game matching two selected genres must not appear twice.
            $query->whereExists(fn (QueryBuilder $genreMatch) => $genreMatch
                ->selectRaw('1')
                ->from('game_genre')
                ->join('genres', 'genres.id', '=', 'game_genre.genre_id')
                ->whereColumn('game_genre.game_id', 'products.game_id')
                ->whereIn('genres.slug', $filters->genreSlugs));
        }

        if ($filters->inStock) {
            $query->whereNotNull('products.min_price_cents');
        }

        if ($filters->priceMinCents !== null) {
            $query->where('products.min_price_cents', '>=', $filters->priceMinCents);
        }

        if ($filters->priceMaxCents !== null) {
            $query->where('products.min_price_cents', '<=', $filters->priceMaxCents);
        }

        return $query;
    }

    /**
     * @return array<int, int> foreign key => product count
     */
    private function countsBy(CatalogFilters $filters, string $column): array
    {
        return $this->filtered($filters)
            ->groupBy($column)
            ->toBase()
            ->selectRaw("{$column} AS facet_id, count(*) AS product_count")
            ->pluck('product_count', 'facet_id')
            ->all();
    }
}
