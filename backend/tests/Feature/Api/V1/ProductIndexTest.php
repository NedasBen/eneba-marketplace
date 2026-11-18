<?php

use App\Services\Checkout\ReleaseExpiredReservations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @return list<string>
 */
function listedProductNames(string $query = ''): array
{
    return test()->getJson('/api/v1/products'.($query === '' ? '' : "?{$query}"))
        ->assertOk()
        ->json('data.*.name');
}

it('returns product cards with prices in cents and the saving against retail', function () {
    $product = catalogProduct('pc', 'europe', attributes: [
        'name' => 'ELDEN RING Steam Key EUROPE',
        'retail_price_cents' => 5999,
        'min_price_cents' => 3489,
        'offers_count' => 3,
    ], game: ['name' => 'ELDEN RING', 'slug' => 'elden-ring', 'cover_image_url' => 'https://cdn.example/cover.jpg']);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.0', [
            'id' => $product->id,
            'name' => 'ELDEN RING Steam Key EUROPE',
            'slug' => $product->slug,
            'game' => ['name' => 'ELDEN RING', 'slug' => 'elden-ring', 'cover_image_url' => 'https://cdn.example/cover.jpg'],
            'platform' => ['slug' => 'pc', 'name' => 'PC', 'activation_service' => 'Steam'],
            'region' => ['slug' => 'europe', 'name' => 'EUROPE'],
            'price_from' => ['amount_cents' => 3489, 'currency' => 'EUR'],
            'retail_price' => ['amount_cents' => 5999, 'currency' => 'EUR'],
            'discount_percent' => 42,
            'offers_count' => 3,
            'in_stock' => true,
        ]);
});

it('hides products without stock unless in_stock=false is passed', function () {
    catalogProduct(attributes: ['name' => 'In stock']);
    catalogProduct(attributes: ['name' => 'Sold out', 'min_price_cents' => null, 'offers_count' => 0]);

    expect(listedProductNames())->toBe(['In stock'])
        ->and(listedProductNames('in_stock=false&sort=name'))->toBe(['In stock', 'Sold out']);

    $this->getJson('/api/v1/products?in_stock=0&sort=name')
        ->assertJsonPath('data.1.price_from', null)
        ->assertJsonPath('data.1.discount_percent', null)
        ->assertJsonPath('data.1.in_stock', false);
});

it('filters by platform', function (string $query, array $expectedNames) {
    catalogProduct('pc', attributes: ['name' => 'PC game']);
    catalogProduct('xbox', attributes: ['name' => 'Xbox game']);
    catalogProduct('playstation', attributes: ['name' => 'PlayStation game']);

    expect(listedProductNames("{$query}&sort=name"))->toBe($expectedNames);
})->with([
    'one platform' => ['platform=pc', ['PC game']],
    'several platforms, array syntax' => ['platform[]=pc&platform[]=xbox', ['PC game', 'Xbox game']],
    'several platforms, comma syntax' => ['platform=xbox,playstation', ['PlayStation game', 'Xbox game']],
]);

it('filters by region', function (string $query, array $expectedNames) {
    catalogProduct(region: 'europe', attributes: ['name' => 'EU key']);
    catalogProduct(region: 'global', attributes: ['name' => 'Global key']);
    catalogProduct(region: 'latam', attributes: ['name' => 'LATAM key']);

    expect(listedProductNames("{$query}&sort=name"))->toBe($expectedNames);
})->with([
    'one region' => ['region=global', ['Global key']],
    'several regions' => ['region=europe,latam', ['EU key', 'LATAM key']],
]);

it('filters by genre without duplicating games that match several genres', function (string $query, array $expectedNames) {
    catalogProduct(genres: ['rpg'], attributes: ['name' => 'RPG only']);
    catalogProduct(genres: ['action'], attributes: ['name' => 'Action only']);
    catalogProduct(genres: ['rpg', 'action'], attributes: ['name' => 'Action RPG']);
    catalogProduct(genres: ['racing'], attributes: ['name' => 'Racing']);

    expect(listedProductNames("{$query}&sort=name"))->toBe($expectedNames);
})->with([
    'one genre' => ['genre=rpg', ['Action RPG', 'RPG only']],
    'several genres' => ['genre=rpg,action', ['Action only', 'Action RPG', 'RPG only']],
]);

it('filters by the "from" price in euros with inclusive bounds', function (string $query, array $expectedNames) {
    foreach ([999, 1000, 2000, 2001] as $cents) {
        catalogProduct(attributes: ['name' => "Costs {$cents}", 'min_price_cents' => $cents]);
    }

    expect(listedProductNames("{$query}&sort=price_asc"))->toBe($expectedNames);
})->with([
    'minimum' => ['price_min=10', ['Costs 1000', 'Costs 2000', 'Costs 2001']],
    'maximum' => ['price_max=20', ['Costs 999', 'Costs 1000', 'Costs 2000']],
    'range' => ['price_min=10&price_max=20', ['Costs 1000', 'Costs 2000']],
    'decimal euros' => ['price_min=9.99&price_max=10', ['Costs 999', 'Costs 1000']],
]);

it('combines different facets with AND', function () {
    catalogProduct('pc', 'europe', ['rpg'], ['name' => 'Match', 'min_price_cents' => 1500]);
    catalogProduct('xbox', 'europe', ['rpg'], ['name' => 'Wrong platform', 'min_price_cents' => 1500]);
    catalogProduct('pc', 'global', ['rpg'], ['name' => 'Wrong region', 'min_price_cents' => 1500]);
    catalogProduct('pc', 'europe', ['action'], ['name' => 'Wrong genre', 'min_price_cents' => 1500]);
    catalogProduct('pc', 'europe', ['rpg'], ['name' => 'Too expensive', 'min_price_cents' => 4500]);

    expect(listedProductNames('platform=pc&region=europe&genre=rpg&price_max=20'))->toBe(['Match']);
});

it('sorts products', function (string $sort, array $expectedNames) {
    // Created in this order, so ids ascend A, B, C, D.
    catalogProduct(attributes: ['name' => 'Bravo', 'sold_count' => 5, 'min_price_cents' => 3000], game: ['released_at' => '2020-05-01']);
    catalogProduct(attributes: ['name' => 'Alpha', 'sold_count' => 9, 'min_price_cents' => 1000], game: ['released_at' => '2024-05-01']);
    catalogProduct(attributes: ['name' => 'Charlie', 'sold_count' => 1, 'min_price_cents' => 2000], game: ['released_at' => '2022-05-01']);
    catalogProduct(attributes: ['name' => 'Delta', 'sold_count' => 9, 'min_price_cents' => null, 'offers_count' => 0], game: ['released_at' => null]);

    $query = $sort === '' ? 'in_stock=false' : "in_stock=false&sort={$sort}";

    expect(listedProductNames($query))->toBe($expectedNames);
})->with([
    'default is popularity' => ['', ['Alpha', 'Delta', 'Bravo', 'Charlie']],
    'popularity, ties broken by id' => ['popularity', ['Alpha', 'Delta', 'Bravo', 'Charlie']],
    'price ascending, unpriced last' => ['price_asc', ['Alpha', 'Charlie', 'Bravo', 'Delta']],
    'price descending, unpriced last' => ['price_desc', ['Bravo', 'Charlie', 'Alpha', 'Delta']],
    // All four share a €59.99 retail price: Alpha -83%, Charlie -67%, Bravo -50%, Delta sold out.
    'biggest discount first, sold out last' => ['discount', ['Alpha', 'Charlie', 'Bravo', 'Delta']],
    'newest release first, undated last' => ['release_date', ['Alpha', 'Charlie', 'Bravo', 'Delta']],
    'name' => ['name', ['Alpha', 'Bravo', 'Charlie', 'Delta']],
]);

it('sorts by the percentage saved against each product\'s own retail price, then by sales', function () {
    catalogProduct(attributes: ['name' => 'Half price, rarely sold', 'retail_price_cents' => 1000, 'min_price_cents' => 500, 'sold_count' => 1]);
    catalogProduct(attributes: ['name' => 'Two thirds off', 'retail_price_cents' => 6000, 'min_price_cents' => 2000, 'sold_count' => 0]);
    catalogProduct(attributes: ['name' => 'Half price, best seller', 'retail_price_cents' => 2000, 'min_price_cents' => 1000, 'sold_count' => 9]);
    catalogProduct(attributes: ['name' => 'Cheapest, no discount', 'retail_price_cents' => 300, 'min_price_cents' => 300, 'sold_count' => 50]);
    // Saves €30 (more than any other in euros) but only 30%: must rank by percentage, not amount.
    catalogProduct(attributes: ['name' => 'Big saving, small percentage', 'retail_price_cents' => 10000, 'min_price_cents' => 7000, 'sold_count' => 0]);

    expect(listedProductNames('sort=discount'))->toBe([
        'Two thirds off',
        'Half price, best seller',
        'Half price, rarely sold',
        'Big saving, small percentage',
        'Cheapest, no discount',
    ]);
});

it('paginates and keeps the active filters in the page links', function () {
    foreach (range(1, 5) as $number) {
        catalogProduct('pc', attributes: ['name' => "Game {$number}"]);
    }

    $this->getJson('/api/v1/products?platform=pc&sort=name&per_page=2&page=3')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Game 5'])
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('links.prev', url('/api/v1/products?platform=pc&sort=name&per_page=2&page=2'));
});

it('defaults to 24 products per page', function () {
    $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('meta.per_page', 24);
});

it('returns 422 for invalid filters', function (string $query, string $field) {
    catalogProduct('pc', 'europe', ['rpg']);

    $this->getJson("/api/v1/products?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown platform' => ['platform=dreamcast', 'platform.0'],
    'unknown region' => ['region=mars', 'region.0'],
    'unknown genre' => ['genre=pc,rpg', 'genre.0'],
    'max below min price' => ['price_min=30&price_max=10', 'price_max'],
    'negative price' => ['price_min=-1', 'price_min'],
    'unknown sort' => ['sort=cheapest', 'sort'],
    'per_page too small' => ['per_page=0', 'per_page'],
    'per_page too large' => ['per_page=49', 'per_page'],
    'non-boolean in_stock' => ['in_stock=maybe', 'in_stock'],
]);

it('uses the same number of queries however many products are on the page', function () {
    // Count the listing's queries only, not the throttled reservation sweep.
    Cache::put(ReleaseExpiredReservations::THROTTLE_KEY, true, 60);

    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/products?per_page=48')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    foreach (['pc', 'xbox', 'playstation'] as $platform) {
        catalogProduct($platform, 'europe', ['rpg']);
    }
    $queriesForThreeProducts = $countQueries();

    foreach (range(1, 17) as $number) {
        catalogProduct(['pc', 'xbox'][$number % 2], ['europe', 'global'][$number % 2], ['action']);
    }
    $queriesForTwentyProducts = $countQueries();

    expect($queriesForTwentyProducts)->toBe($queriesForThreeProducts);
});
