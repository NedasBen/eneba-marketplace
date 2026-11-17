<?php

use App\Enums\GamePlatform;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Region;
use App\Services\Search\SearchVocabulary;
use Illuminate\Support\Facades\DB;

it('suggests the corrected game with the original and corrected query', function () {
    catalogProduct(game: ['name' => 'The Witcher 3: Wild Hunt', 'slug' => 'the-witcher-3-wild-hunt']);
    catalogProduct(game: ['name' => 'Monster Hunter Wilds']);

    $this->getJson('/api/v1/search/suggest?q=wichter')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'the-witcher-3-wild-hunt')
        ->assertJsonPath('query', ['original' => 'wichter', 'corrected' => 'witcher']);
});

it('returns one entry per game with its cheapest in-stock product', function () {
    $pcProduct = catalogProduct('pc', 'europe', attributes: ['min_price_cents' => 2999], game: ['name' => 'Cyberpunk 2077', 'slug' => 'cyberpunk-2077', 'cover_image_url' => 'https://cdn.example/cp.jpg']);
    $game = $pcProduct->game;

    $xboxProduct = Product::factory()->for($game)
        ->for(Platform::factory()->forPlatform(GamePlatform::Xbox))
        ->for(Region::query()->where('slug', 'europe')->sole())
        ->create(['min_price_cents' => 1999, 'offers_count' => 2]);
    Product::factory()->for($game)
        ->for(Platform::factory()->forPlatform(GamePlatform::PlayStation))
        ->for(Region::query()->where('slug', 'europe')->sole())
        ->create(['min_price_cents' => null, 'offers_count' => 0]);

    $this->getJson('/api/v1/search/suggest?q=cyberpunk')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0', [
            'name' => 'Cyberpunk 2077',
            'slug' => 'cyberpunk-2077',
            'cover_image_url' => 'https://cdn.example/cp.jpg',
            'price_from' => ['amount_cents' => 1999, 'currency' => 'EUR'],
            'platforms' => ['pc', 'xbox'],
            'product_slug' => $xboxProduct->slug,
            'in_stock' => true,
        ]);
});

it('still suggests sold-out games, without a price', function () {
    $product = catalogProduct(attributes: ['min_price_cents' => null, 'offers_count' => 0], game: ['name' => 'Fallout 4']);

    $this->getJson('/api/v1/search/suggest?q=fallout')
        ->assertOk()
        ->assertJsonPath('data.0.price_from', null)
        ->assertJsonPath('data.0.platforms', [])
        ->assertJsonPath('data.0.product_slug', $product->slug)
        ->assertJsonPath('data.0.in_stock', false);
});

it('returns at most eight games, best sellers first among equal matches', function () {
    // Identical names score identically, so sales decide the order.
    foreach (range(1, 10) as $soldCount) {
        catalogProduct(attributes: ['sold_count' => $soldCount], game: ['name' => 'Space Explorer', 'slug' => "space-explorer-{$soldCount}"]);
    }

    $slugs = $this->getJson('/api/v1/search/suggest?q=space')->assertOk()->json('data.*.slug');

    expect($slugs)->toBe(array_map(fn (int $sold): string => "space-explorer-{$sold}", range(10, 3)));
});

it('returns no suggestions for missing or one-character queries', function (string $query) {
    catalogProduct(game: ['name' => 'ELDEN RING']);

    $this->getJson('/api/v1/search/suggest'.$query)
        ->assertOk()
        ->assertExactJson(['data' => [], 'query' => null]);
})->with(['no q' => [''], 'one character' => ['?q=e'], 'only punctuation' => ['?q=%20!%20']]);

it('returns 422 for an overly long query', function () {
    $this->getJson('/api/v1/search/suggest?q='.str_repeat('a', 101))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

it('answers from one query once the vocabulary is cached', function () {
    foreach (['ELDEN RING', 'Cyberpunk 2077', 'Hollow Knight'] as $name) {
        catalogProduct(game: ['name' => $name]);
    }
    $this->getJson('/api/v1/search/suggest?q=cyberpnk')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/v1/search/suggest?q=cyberpnk')->assertOk()->assertJsonPath('data.0.name', 'Cyberpunk 2077');
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(cache()->has(SearchVocabulary::CACHE_KEY))->toBeTrue();
});

it('limits autocomplete to 60 requests a minute per client', function () {
    for ($request = 1; $request <= 60; $request++) {
        $this->getJson('/api/v1/search/suggest?q=el')->assertOk();
    }

    $this->getJson('/api/v1/search/suggest?q=el')->assertTooManyRequests();
});
