<?php

use App\Enums\GamePlatform;
use App\Models\Game;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * A store product on a real platform/region (reused by slug), for a fresh game
 * with the given genres. In stock at €19.99 unless overridden.
 *
 * @param  list<string>  $genres  genre slugs
 * @param  array<string, mixed>  $attributes  product attributes
 * @param  array<string, mixed>  $game  game attributes
 */
function catalogProduct(string $platform = 'pc', string $region = 'europe', array $genres = [], array $attributes = [], array $game = []): Product
{
    $platformModel = Platform::query()->firstWhere('slug', $platform)
        ?? Platform::factory()->forPlatform(GamePlatform::from($platform))->create();
    $regionModel = Region::query()->firstWhere('slug', $region)
        ?? Region::factory()->create(['slug' => $region, 'name' => Str::upper(str_replace('-', ' ', $region))]);

    $gameModel = Game::factory()->create($game);
    $gameModel->genres()->attach(array_map(
        fn (string $slug): int => (Genre::query()->firstWhere('slug', $slug)
            ?? Genre::factory()->create(['slug' => $slug, 'name' => Str::title(str_replace('-', ' ', $slug))]))->id,
        $genres,
    ));

    return Product::factory()
        ->for($gameModel)
        ->for($platformModel)
        ->for($regionModel)
        ->create(array_replace(['retail_price_cents' => 5999, 'min_price_cents' => 1999, 'offers_count' => 2], $attributes));
}
