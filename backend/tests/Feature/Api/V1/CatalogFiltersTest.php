<?php

use App\Enums\GamePlatform;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Region;

/**
 * Two PC/EUROPE RPGs at €5 and €15, one Xbox/GLOBAL action game at €25,
 * one sold-out PC/EUROPE RPG, plus options that have no products at all.
 */
function seedFacetCatalog(): void
{
    catalogProduct('pc', 'europe', ['rpg'], ['min_price_cents' => 500]);
    catalogProduct('pc', 'europe', ['rpg'], ['min_price_cents' => 1500]);
    catalogProduct('xbox', 'global', ['action'], ['min_price_cents' => 2500]);
    catalogProduct('pc', 'europe', ['rpg'], ['min_price_cents' => null, 'offers_count' => 0]);

    Platform::factory()->forPlatform(GamePlatform::NintendoSwitch)->create();
    Region::factory()->create(['slug' => 'latam', 'name' => 'LATAM']);
    Genre::factory()->create(['slug' => 'sports', 'name' => 'Sports']);
}

/**
 * @return array<string, int> slug => count
 */
function facetCounts(array $facet): array
{
    return array_column($facet, 'count', 'slug');
}

it('lists every platform, region and genre from the database, including ones without products', function () {
    seedFacetCatalog();

    $this->getJson('/api/v1/catalog/filters')
        ->assertOk()
        ->assertJsonPath('data.platforms', [
            ['slug' => 'pc', 'name' => 'PC', 'activation_service' => 'Steam', 'count' => 2],
            ['slug' => 'xbox', 'name' => 'Xbox', 'activation_service' => 'Xbox Live', 'count' => 1],
            ['slug' => 'nintendo-switch', 'name' => 'Nintendo Switch', 'activation_service' => 'Nintendo eShop', 'count' => 0],
        ])
        ->assertJsonPath('data.regions', [
            ['slug' => 'europe', 'name' => 'EUROPE', 'count' => 2],
            ['slug' => 'global', 'name' => 'GLOBAL', 'count' => 1],
            ['slug' => 'latam', 'name' => 'LATAM', 'count' => 0],
        ])
        ->assertJsonPath('data.genres', [
            ['slug' => 'action', 'name' => 'Action', 'count' => 1],
            ['slug' => 'rpg', 'name' => 'Rpg', 'count' => 2],
            ['slug' => 'sports', 'name' => 'Sports', 'count' => 0],
        ]);
});

it('counts each facet with every other active filter but not its own', function () {
    seedFacetCatalog();

    $facets = $this->getJson('/api/v1/catalog/filters?platform=pc')->assertOk()->json('data');

    expect(facetCounts($facets['platforms']))->toBe(['pc' => 2, 'xbox' => 1, 'nintendo-switch' => 0])
        ->and(facetCounts($facets['regions']))->toBe(['europe' => 2, 'global' => 0, 'latam' => 0])
        ->and(facetCounts($facets['genres']))->toBe(['action' => 0, 'rpg' => 2, 'sports' => 0]);
});

it('counts out-of-stock products only when in_stock=false', function () {
    seedFacetCatalog();

    $facets = $this->getJson('/api/v1/catalog/filters?in_stock=false')->assertOk()->json('data');

    expect(facetCounts($facets['platforms'])['pc'])->toBe(3)
        ->and(facetCounts($facets['genres'])['rpg'])->toBe(3);
});

it('reports the price range of the current results, ignoring the price filter itself', function (string $query, ?int $expectedMin, ?int $expectedMax) {
    seedFacetCatalog();

    $this->getJson("/api/v1/catalog/filters?{$query}")
        ->assertOk()
        ->assertJsonPath('data.price.min.amount_cents', $expectedMin)
        ->assertJsonPath('data.price.max.amount_cents', $expectedMax);
})->with([
    'no filters' => ['', 500, 2500],
    'price filter is ignored' => ['price_min=10&price_max=12', 500, 2500],
    'other facets narrow it' => ['platform=pc', 500, 1500],
    'no matching products' => ['region=latam', null, null],
]);

it('lists the sort and page-size options so the frontend hardcodes none', function () {
    $this->getJson('/api/v1/catalog/filters')
        ->assertOk()
        ->assertJsonPath('data.sorts', [
            ['value' => 'relevance', 'label' => 'Relevance', 'requires_query' => true],
            ['value' => 'popularity', 'label' => 'Best selling', 'requires_query' => false],
            ['value' => 'price_asc', 'label' => 'Price: low to high', 'requires_query' => false],
            ['value' => 'price_desc', 'label' => 'Price: high to low', 'requires_query' => false],
            ['value' => 'release_date', 'label' => 'Newest releases', 'requires_query' => false],
            ['value' => 'name', 'label' => 'Name: A to Z', 'requires_query' => false],
        ])
        ->assertJsonPath('data.default_sort', 'popularity')
        ->assertJsonPath('data.per_page', ['options' => [12, 24, 48], 'default' => 24]);
});

it('returns 422 for unknown filter values', function () {
    $this->getJson('/api/v1/catalog/filters?genre=not-a-genre')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('genre.0');
});
