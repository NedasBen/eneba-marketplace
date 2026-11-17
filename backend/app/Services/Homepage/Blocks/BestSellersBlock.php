<?php

namespace App\Services\Homepage\Blocks;

use App\Models\HomepageBlock;
use App\Models\Product;
use App\Services\Homepage\ProductRailBlock;
use Illuminate\Database\Eloquent\Builder;

/** In-stock products by keys sold. */
class BestSellersBlock extends ProductRailBlock
{
    public static function type(): string
    {
        return 'best_sellers';
    }

    protected function products(HomepageBlock $block): Builder
    {
        return Product::query()
            ->whereNotNull('min_price_cents')
            ->orderByDesc('sold_count')
            ->orderBy('id');
    }

    protected function seeAllQuery(HomepageBlock $block): string
    {
        return '?sort=popularity';
    }
}
