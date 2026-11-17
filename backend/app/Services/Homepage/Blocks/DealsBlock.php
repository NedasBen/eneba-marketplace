<?php

namespace App\Services\Homepage\Blocks;

use App\Models\HomepageBlock;
use App\Models\Product;
use App\Services\Homepage\ProductRailBlock;
use Illuminate\Database\Eloquent\Builder;

/**
 * Biggest savings against the retail price, from `settings.min_discount_percent`
 * (default 30) upwards.
 */
class DealsBlock extends ProductRailBlock
{
    public const DEFAULT_MIN_DISCOUNT_PERCENT = 30;

    public static function type(): string
    {
        return 'deals';
    }

    protected function products(HomepageBlock $block): Builder
    {
        $minDiscount = (float) ($block->settings['min_discount_percent'] ?? self::DEFAULT_MIN_DISCOUNT_PERCENT);

        return Product::query()
            ->whereNotNull('min_price_cents')
            ->whereRaw(Product::DISCOUNT_PERCENT_SQL.' >= ?', [$minDiscount])
            ->orderByRaw(Product::DISCOUNT_PERCENT_SQL.' DESC')
            ->orderByDesc('sold_count')
            ->orderBy('id');
    }

    /** The full store, biggest discount first (same ordering as this rail). */
    protected function seeAllQuery(HomepageBlock $block): string
    {
        return '?sort=discount';
    }
}
