<?php

namespace App\Services\Homepage\Blocks;

use App\Models\Banner;
use App\Models\HomepageBlock;
use App\Services\Homepage\HomepageBlockProvider;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hero carousel: active banners whose schedule includes now, by position,
 * each linking to its product with that product's current "from" price.
 */
class HeroBannerBlock implements HomepageBlockProvider
{
    public const DEFAULT_LIMIT = 5;

    public static function type(): string
    {
        return 'hero_banner';
    }

    public static function layout(): string
    {
        return 'hero';
    }

    /**
     * @return array{slides: list<array<string, mixed>>}|null
     */
    public function data(HomepageBlock $block): ?array
    {
        $now = now();

        $banners = Banner::query()
            ->where('is_active', true)
            ->where(fn (Builder $starts) => $starts->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $ends) => $ends->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->with(['product:id,game_id,slug,min_price_cents', 'product.game:id,cover_image_url'])
            ->orderBy('position')
            ->orderBy('id')
            ->limit(max(1, (int) ($block->settings['limit'] ?? self::DEFAULT_LIMIT)))
            ->get();

        if ($banners->isEmpty()) {
            return null;
        }

        return [
            'slides' => $banners->map(fn (Banner $banner): array => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'image_url' => $banner->image_url,
                // Portrait art for the side tiles next to the main slide.
                'cover_image_url' => $banner->product?->game?->cover_image_url,
                'cta_label' => $banner->cta_label,
                'product_slug' => $banner->product?->slug,
                'price_from' => Money::fromNullableCents($banner->product?->min_price_cents),
            ])->all(),
        ];
    }
}
