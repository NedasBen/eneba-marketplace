<?php

use App\Enums\GamePlatform;
use App\Models\Banner;
use App\Models\HomepageBlock;
use App\Models\Platform;
use App\Models\Product;
use App\Services\Homepage\Blocks\BestSellersBlock;
use App\Services\Homepage\Blocks\DealsBlock;
use App\Services\Homepage\Blocks\GenreRailBlock;
use App\Services\Homepage\Blocks\HeroBannerBlock;
use App\Services\Homepage\Blocks\NewReleasesBlock;

/**
 * @param  array<string, mixed>  $settings
 * @return list<string> product names in the block's order
 */
function railProductNames(string $providerClass, array $settings = []): array
{
    $data = app($providerClass)->data(HomepageBlock::factory()->make(['type' => $providerClass::type(), 'settings' => $settings]));

    return $data === null ? [] : array_column($data['products'], 'name');
}

/**
 * Another edition of the same game on another platform, to test "one product per game".
 *
 * @param  array<string, mixed>  $attributes
 */
function editionOf(Product $product, GamePlatform $platform, array $attributes): Product
{
    return Product::factory()
        ->for($product->game)
        ->for(Platform::query()->firstWhere('slug', $platform->value) ?? Platform::factory()->forPlatform($platform)->create())
        ->for($product->region)
        ->create($attributes);
}

describe('best sellers', function () {
    it('lists in-stock products by keys sold, honouring the limit', function () {
        catalogProduct(attributes: ['name' => 'Bronze', 'sold_count' => 5]);
        catalogProduct(attributes: ['name' => 'Gold', 'sold_count' => 50]);
        catalogProduct(attributes: ['name' => 'Silver', 'sold_count' => 20]);
        catalogProduct(attributes: ['name' => 'Sold out', 'sold_count' => 99, 'min_price_cents' => null]);

        expect(railProductNames(BestSellersBlock::class))->toBe(['Gold', 'Silver', 'Bronze'])
            ->and(railProductNames(BestSellersBlock::class, ['limit' => 2]))->toBe(['Gold', 'Silver']);
    });

    it('links "See all" to the store sorted by popularity', function () {
        catalogProduct();

        expect(app(BestSellersBlock::class)->data(HomepageBlock::factory()->make(['type' => 'best_sellers']))['see_all'])->toBe('?sort=popularity');
    });

    it('returns nothing when no product is in stock', function () {
        catalogProduct(attributes: ['min_price_cents' => null]);

        expect(app(BestSellersBlock::class)->data(HomepageBlock::factory()->make(['type' => 'best_sellers'])))->toBeNull();
    });
});

describe('deals', function () {
    it('lists the biggest savings first, from the minimum discount up', function () {
        catalogProduct(attributes: ['name' => '50% off', 'retail_price_cents' => 4000, 'min_price_cents' => 2000]);
        catalogProduct(attributes: ['name' => '75% off', 'retail_price_cents' => 4000, 'min_price_cents' => 1000]);
        catalogProduct(attributes: ['name' => '30% off', 'retail_price_cents' => 1000, 'min_price_cents' => 700]);
        catalogProduct(attributes: ['name' => '20% off', 'retail_price_cents' => 1000, 'min_price_cents' => 800]);

        expect(railProductNames(DealsBlock::class))->toBe(['75% off', '50% off', '30% off'])
            ->and(railProductNames(DealsBlock::class, ['min_discount_percent' => 60]))->toBe(['75% off']);
    });

    it('has no "See all" link because the store cannot sort by discount', function () {
        catalogProduct(attributes: ['retail_price_cents' => 4000, 'min_price_cents' => 1000]);

        expect(app(DealsBlock::class)->data(HomepageBlock::factory()->make(['type' => 'deals']))['see_all'])->toBeNull();
    });
});

describe('new releases', function () {
    it('lists released games newest first and leaves out unreleased and undated ones', function () {
        catalogProduct(attributes: ['name' => '2023 game'], game: ['released_at' => '2023-03-01']);
        catalogProduct(attributes: ['name' => '2025 game'], game: ['released_at' => '2025-03-01']);
        catalogProduct(attributes: ['name' => 'Coming soon'], game: ['released_at' => now()->addMonth()]);
        catalogProduct(attributes: ['name' => 'Undated'], game: ['released_at' => null]);

        expect(railProductNames(NewReleasesBlock::class))->toBe(['2025 game', '2023 game']);
    });

    it('shows each game once, using its best-selling in-stock product', function () {
        $pcEdition = catalogProduct(attributes: ['name' => 'PC edition', 'sold_count' => 3], game: ['released_at' => '2025-01-01']);
        editionOf($pcEdition, GamePlatform::Xbox, ['name' => 'Xbox edition', 'sold_count' => 9, 'min_price_cents' => 1500]);
        editionOf($pcEdition, GamePlatform::PlayStation, ['name' => 'Sold-out edition', 'sold_count' => 99, 'min_price_cents' => null, 'slug' => 'sold-out-edition']);

        expect(railProductNames(NewReleasesBlock::class))->toBe(['Xbox edition']);
    });
});

describe('genre rail', function () {
    it('lists best sellers in the configured genre, one product per game', function () {
        $rpg = catalogProduct(genres: ['rpg'], attributes: ['name' => 'RPG on PC', 'sold_count' => 4]);
        editionOf($rpg, GamePlatform::Xbox, ['name' => 'RPG on Xbox', 'sold_count' => 1, 'min_price_cents' => 999]);
        catalogProduct(genres: ['rpg', 'action'], attributes: ['name' => 'Action RPG', 'sold_count' => 8]);
        catalogProduct(genres: ['racing'], attributes: ['name' => 'Racer', 'sold_count' => 50]);

        expect(railProductNames(GenreRailBlock::class, ['genre' => 'rpg']))->toBe(['Action RPG', 'RPG on PC']);
    });

    it('links "See all" to the store filtered by the genre', function () {
        catalogProduct(genres: ['rpg']);

        expect(app(GenreRailBlock::class)->data(HomepageBlock::factory()->make(['type' => 'genre_rail', 'settings' => ['genre' => 'rpg']]))['see_all'])->toBe('?genre=rpg');
    });

    it('returns nothing for a missing or unknown genre', function (array $settings) {
        catalogProduct(genres: ['rpg']);

        expect(app(GenreRailBlock::class)->data(HomepageBlock::factory()->make(['type' => 'genre_rail', 'settings' => $settings])))->toBeNull();
    })->with(['no genre' => [[]], 'unknown genre' => [['genre' => 'not-a-genre']]]);
});

describe('hero banner', function () {
    it('shows active, currently scheduled banners in position order with their product link and price', function () {
        $product = catalogProduct(attributes: ['slug' => 'elden-ring-steam-key-global', 'min_price_cents' => 3489]);
        Banner::factory()->for($product)->create(['title' => 'Second', 'position' => 2]);
        Banner::factory()->for($product)->create(['title' => 'First', 'position' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        Banner::factory()->for($product)->create(['title' => 'Paused', 'position' => 3, 'is_active' => false]);
        Banner::factory()->for($product)->create(['title' => 'Not started', 'position' => 4, 'starts_at' => now()->addDay()]);
        Banner::factory()->for($product)->create(['title' => 'Ended', 'position' => 5, 'ends_at' => now()->subMinute()]);

        $slides = app(HeroBannerBlock::class)->data(HomepageBlock::factory()->make(['type' => 'hero_banner']))['slides'];

        expect(array_column($slides, 'title'))->toBe(['First', 'Second'])
            ->and($slides[0])->toMatchArray([
                'product_slug' => 'elden-ring-steam-key-global',
                'price_from' => ['amount_cents' => 3489, 'currency' => 'EUR'],
            ]);
    });

    it('returns nothing when no banner is live', function () {
        Banner::factory()->create(['is_active' => false]);

        expect(app(HeroBannerBlock::class)->data(HomepageBlock::factory()->make(['type' => 'hero_banner'])))->toBeNull();
    });
});
