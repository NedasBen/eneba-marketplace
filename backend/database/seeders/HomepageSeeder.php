<?php

namespace Database\Seeders;

use App\Services\Homepage\Blocks\BestSellersBlock;
use App\Services\Homepage\Blocks\DealsBlock;
use App\Services\Homepage\Blocks\GenreRailBlock;
use App\Services\Homepage\Blocks\HeroBannerBlock;
use App\Services\Homepage\Blocks\NewReleasesBlock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Homepage layout and hero banners. Runs after product stats are computed,
 * so banners only point at products that are actually in stock.
 */
class HomepageSeeder extends Seeder
{
    public const BANNER_COUNT = 4;

    public function run(): void
    {
        $now = now();

        $blocks = [
            [HeroBannerBlock::type(), 'Featured', []],
            [BestSellersBlock::type(), 'Best sellers', ['limit' => 12]],
            [DealsBlock::type(), 'Top deals', ['limit' => 12, 'min_discount_percent' => 30]],
            [NewReleasesBlock::type(), 'New releases', ['limit' => 12]],
            [GenreRailBlock::type(), 'Top RPGs', ['limit' => 12, 'genre' => 'rpg']],
        ];

        DB::table('homepage_blocks')->insert(array_map(fn (array $block, int $index): array => [
            'type' => $block[0],
            'title' => $block[1],
            'position' => $index + 1,
            'is_active' => true,
            'settings' => json_encode((object) $block[2]),
            'created_at' => $now,
            'updated_at' => $now,
        ], $blocks, array_keys($blocks)));

        // Highest-rated games with wide hero art and an in-stock PC product.
        $bannerProducts = DB::table('products')
            ->join('games', 'games.id', '=', 'products.game_id')
            ->join('platforms', 'platforms.id', '=', 'products.platform_id')
            ->where('platforms.slug', 'pc')
            ->whereNotNull('products.min_price_cents')
            ->whereNotNull('games.hero_image_url')
            ->orderByRaw('games.metacritic DESC NULLS LAST')
            ->orderByDesc('games.released_at')
            ->orderBy('products.id')
            ->get(['products.id', 'games.id as game_id', 'games.name', 'games.short_description', 'games.hero_image_url'])
            ->unique('game_id')
            ->take(self::BANNER_COUNT)
            ->values();

        DB::table('banners')->insert($bannerProducts->map(fn (object $product, int $index): array => [
            'title' => $product->name,
            'subtitle' => Str::limit($product->short_description, 120),
            'image_url' => $product->hero_image_url,
            'cta_label' => 'Buy now',
            'product_id' => $product->id,
            'position' => $index + 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }
}
