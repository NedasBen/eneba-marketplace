<?php

use App\Enums\GamePlatform;
use App\Models\Offer;
use App\Models\Platform;
use App\Models\Product;
use App\Models\ProductKey;
use App\Models\Region;
use App\Models\Seller;
use App\Services\Checkout\ReleaseExpiredReservations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * An offer by a named seller with `$keys` keys in stock.
 */
function offerFor(Product $product, int $priceCents, string $seller, float $rating = 98.5, int $keys = 3, bool $isActive = true): Offer
{
    $offer = Offer::factory()
        ->for($product)
        ->for(Seller::factory()->create(['name' => $seller, 'rating' => $rating, 'feedback_count' => 1200, 'country_code' => 'LT']))
        ->create(['price_cents' => $priceCents, 'is_active' => $isActive]);

    if ($keys > 0) {
        ProductKey::factory()->count($keys)->for($offer)->create();
    }

    return $offer;
}

function productPage(string $slug): TestResponse
{
    return test()->getJson("/api/v1/products/{$slug}");
}

it('returns the product with full game details', function () {
    $product = catalogProduct('pc', 'global', ['rpg', 'action'], ['slug' => 'elden-ring-steam-key-global', 'name' => 'ELDEN RING Steam Key GLOBAL'], [
        'name' => 'ELDEN RING',
        'description' => "Rise, Tarnished.\n\nA vast world.",
        'screenshots' => ['https://cdn.example/1.jpg', 'https://cdn.example/2.jpg'],
        'developer' => 'FromSoftware, Inc.',
        'publisher' => 'Bandai Namco',
        'released_at' => '2022-02-25',
        'metacritic' => 94,
    ]);

    productPage($product->slug)
        ->assertOk()
        ->assertJsonPath('data.name', 'ELDEN RING Steam Key GLOBAL')
        ->assertJsonPath('data.platform', ['slug' => 'pc', 'name' => 'PC', 'activation_service' => 'Steam'])
        ->assertJsonPath('data.region', ['slug' => 'global', 'name' => 'GLOBAL'])
        ->assertJsonPath('data.game.description', "Rise, Tarnished.\n\nA vast world.")
        ->assertJsonPath('data.game.screenshots', ['https://cdn.example/1.jpg', 'https://cdn.example/2.jpg'])
        ->assertJsonPath('data.game.genres', [['slug' => 'action', 'name' => 'Action'], ['slug' => 'rpg', 'name' => 'Rpg']])
        ->assertJsonPath('data.game.developer', 'FromSoftware, Inc.')
        ->assertJsonPath('data.game.publisher', 'Bandai Namco')
        ->assertJsonPath('data.game.released_at', '2022-02-25')
        ->assertJsonPath('data.game.metacritic', 94);
});

it('lists purchasable offers cheapest first with seller details and stock', function () {
    $product = catalogProduct();
    offerFor($product, 2499, 'Pricier', keys: 2);
    offerFor($product, 1999, 'Cheapest', rating: 97.2, keys: 7);

    productPage($product->slug)
        ->assertOk()
        ->assertJsonPath('data.offers.*.seller.name', ['Cheapest', 'Pricier'])
        ->assertJsonPath('data.offers.0', [
            'id' => $product->offers()->orderBy('price_cents')->value('id'),
            'price' => ['amount_cents' => 1999, 'currency' => 'EUR'],
            'stock' => 7,
            'seller' => [
                'name' => 'Cheapest',
                'slug' => Seller::query()->where('name', 'Cheapest')->value('slug'),
                'rating' => 97.2,
                'feedback_count' => 1200,
                'country_code' => 'LT',
            ],
        ]);
});

it('breaks price ties in favour of the better-rated seller', function () {
    $product = catalogProduct();
    offerFor($product, 1999, 'Good', rating: 91.0);
    offerFor($product, 1999, 'Great', rating: 99.1);

    productPage($product->slug)->assertJsonPath('data.offers.*.seller.name', ['Great', 'Good']);
});

it('leaves out inactive offers and offers without stock', function () {
    $product = catalogProduct();
    offerFor($product, 1500, 'Paused', isActive: false);
    offerFor($product, 1600, 'Sold out', keys: 0);
    $held = offerFor($product, 1700, 'All keys held', keys: 0);
    ProductKey::factory()->for($held)->reserved(now()->addMinutes(10))->create();
    offerFor($product, 1800, 'Available');

    productPage($product->slug)->assertJsonPath('data.offers.*.seller.name', ['Available']);
});

it('counts keys whose reservation expired as stock, but not keys still on hold', function () {
    $product = catalogProduct();
    $offer = offerFor($product, 1500, 'Seller', keys: 2);
    ProductKey::factory()->for($offer)->reserved(now()->subMinute())->create();
    ProductKey::factory()->for($offer)->reserved(now()->addMinutes(10))->create();
    ProductKey::factory()->for($offer)->sold()->create();

    productPage($product->slug)->assertJsonPath('data.offers.0.stock', 3);
});

it('lists every edition of the game and marks the current one', function () {
    $current = catalogProduct('pc', 'europe', attributes: ['slug' => 'game-steam-europe', 'min_price_cents' => 1999], game: ['name' => 'Some Game']);
    Product::factory()->for($current->game)
        ->for(Platform::factory()->forPlatform(GamePlatform::Xbox))
        ->for(Region::query()->where('slug', 'europe')->sole())
        ->create(['slug' => 'game-xbox-europe', 'min_price_cents' => null]);
    catalogProduct('pc', 'europe', attributes: ['slug' => 'other-game'], game: ['name' => 'Other Game']);

    productPage('game-steam-europe')
        ->assertOk()
        ->assertJsonPath('data.editions.*.slug', ['game-steam-europe', 'game-xbox-europe'])
        ->assertJsonPath('data.editions.0.is_current', true)
        ->assertJsonPath('data.editions.0.price_from', ['amount_cents' => 1999, 'currency' => 'EUR'])
        ->assertJsonPath('data.editions.1', [
            'slug' => 'game-xbox-europe',
            'name' => Product::query()->where('slug', 'game-xbox-europe')->value('name'),
            'platform' => ['slug' => 'xbox', 'name' => 'Xbox', 'activation_service' => 'Xbox Live'],
            'region' => ['slug' => 'europe', 'name' => 'EUROPE'],
            'price_from' => null,
            'in_stock' => false,
            'is_current' => false,
        ]);
});

it('still shows a sold-out product, with no offers and its editions', function () {
    $product = catalogProduct(attributes: ['min_price_cents' => null, 'offers_count' => 0]);
    offerFor($product, 1500, 'Empty', keys: 0);

    productPage($product->slug)
        ->assertOk()
        ->assertJsonPath('data.in_stock', false)
        ->assertJsonPath('data.price_from', null)
        ->assertJsonPath('data.offers', [])
        ->assertJsonCount(1, 'data.editions');
});

it('returns 404 with a plain message for an unknown slug', function () {
    productPage('no-such-product')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Product not found.']);
});

it('uses the same number of queries however many offers and editions exist', function () {
    // Count the product page's queries only, not the throttled reservation sweep.
    Cache::put(ReleaseExpiredReservations::THROTTLE_KEY, true, 60);

    $countQueries = function (string $slug): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        productPage($slug)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $small = catalogProduct(genres: ['rpg']);
    offerFor($small, 1000, 'Only seller');
    $queriesForOneOffer = $countQueries($small->slug);

    $large = catalogProduct(genres: ['rpg', 'action'], attributes: ['slug' => 'large-edition']);
    foreach (range(1, 8) as $number) {
        offerFor($large, 1000 + $number, "Seller {$number}");
    }
    foreach ([GamePlatform::Xbox, GamePlatform::PlayStation] as $platform) {
        Product::factory()->for($large->game)
            ->for(Platform::query()->firstWhere('slug', $platform->value) ?? Platform::factory()->forPlatform($platform)->create())
            ->for($large->region)
            ->create();
    }

    expect($countQueries('large-edition'))->toBe($queriesForOneOffer);
});
