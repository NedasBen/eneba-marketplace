<?php

namespace App\Services\Homepage;

use App\Http\Resources\ProductCardResource;
use App\Models\HomepageBlock;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Shared base for blocks rendered as a horizontal row of product cards.
 * Subclasses only decide which products, in which order.
 */
abstract class ProductRailBlock implements HomepageBlockProvider
{
    public const DEFAULT_LIMIT = 12;

    public const MAX_LIMIT = 24;

    public static function layout(): string
    {
        return 'product_rail';
    }

    /**
     * The products to show, already ordered; the limit is applied here.
     *
     * @return Builder<Product>|null null when the block's settings make it empty
     */
    abstract protected function products(HomepageBlock $block): ?Builder;

    /** Store query for the "See all" button, e.g. "?sort=release_date"; null hides it. */
    abstract protected function seeAllQuery(HomepageBlock $block): ?string;

    /**
     * @return array{products: list<array<string, mixed>>, see_all: string|null}|null
     */
    public function data(HomepageBlock $block): ?array
    {
        $query = $this->products($block);

        if ($query === null) {
            return null;
        }

        $products = $query->forCard()->limit($this->limit($block))->get();

        if ($products->isEmpty()) {
            return null;
        }

        return [
            'products' => ProductCardResource::collection($products)->resolve(),
            'see_all' => $this->seeAllQuery($block),
        ];
    }

    protected function limit(HomepageBlock $block): int
    {
        return max(1, min(self::MAX_LIMIT, (int) ($block->settings['limit'] ?? self::DEFAULT_LIMIT)));
    }

    /**
     * One product per game: for each game matching `$candidates`, its
     * best-selling in-stock product. Avoids a rail showing the same game on
     * four platforms. Returns ids with the game's release date for ordering.
     *
     * @param  QueryBuilder  $candidates  a query on `products` (may join `games`)
     */
    protected function bestProductPerGame(QueryBuilder $candidates): QueryBuilder
    {
        return $candidates
            ->whereNotNull('products.min_price_cents')
            ->selectRaw('DISTINCT ON (products.game_id) products.id, products.game_id')
            ->orderBy('products.game_id')
            ->orderByDesc('products.sold_count')
            ->orderBy('products.id');
    }

    /**
     * @return Builder<Product>
     */
    protected function productsFromPicks(QueryBuilder $picks): Builder
    {
        return Product::query()
            ->select('products.*')
            ->joinSub($picks, 'pick', 'pick.id', '=', 'products.id');
    }

    protected function productsTable(): QueryBuilder
    {
        return DB::table('products');
    }
}
