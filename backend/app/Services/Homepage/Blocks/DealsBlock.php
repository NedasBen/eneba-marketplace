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

    private const DISCOUNT_SQL = '(products.retail_price_cents - products.min_price_cents) * 100.0 / products.retail_price_cents';

    public static function type(): string
    {
        return 'deals';
    }

    protected function products(HomepageBlock $block): Builder
    {
        $minDiscount = (float) ($block->settings['min_discount_percent'] ?? self::DEFAULT_MIN_DISCOUNT_PERCENT);

        return Product::query()
            ->whereNotNull('min_price_cents')
            ->whereRaw(self::DISCOUNT_SQL.' >= ?', [$minDiscount])
            ->orderByRaw(self::DISCOUNT_SQL.' DESC')
            ->orderByDesc('sold_count')
            ->orderBy('id');
    }

    /** The store has no "by discount" sort, so there is nothing faithful to link to. */
    protected function seeAllQuery(HomepageBlock $block): ?string
    {
        return null;
    }
}
