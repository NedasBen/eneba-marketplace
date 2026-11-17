<?php

use App\Enums\ProductKeyStatus;
use App\Models\Banner;
use App\Models\Game;
use App\Models\HomepageBlock;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\ProductStockAggregator;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OrderHistorySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seed the full marketplace from a three-game fixture
 * (ELDEN RING: pc/ps/xbox, Hollow Knight: all four, Lethal Company: pc only).
 */
function seedFixtureMarketplace(): void
{
    app()->when(CatalogSeeder::class)->needs('$gamesPath')->give(base_path('tests/Fixtures/catalog/games.json'));

    test()->seed(DatabaseSeeder::class);
}

it('creates products only for the platforms each game is really sold on, in 1-3 regions', function () {
    seedFixtureMarketplace();

    $platformsByGame = Game::query()->with('products.platform')->get()
        ->mapWithKeys(fn (Game $game): array => [$game->name => $game->products->pluck('platform.slug')->unique()->sort()->values()->all()]);

    expect($platformsByGame->all())->toEqual([
        'ELDEN RING' => ['pc', 'playstation', 'xbox'],
        'Hollow Knight' => ['nintendo-switch', 'pc', 'playstation', 'xbox'],
        'Lethal Company' => ['pc'],
    ]);

    $regionsPerGamePlatform = Product::query()->toBase()
        ->selectRaw('count(*) as regions')->groupBy('game_id', 'platform_id')->pluck('regions');

    expect($regionsPerGamePlatform->min())->toBeGreaterThanOrEqual(1)
        ->and($regionsPerGamePlatform->max())->toBeLessThanOrEqual(3);
});

it('gives every product 2-5 offers priced at most at retail and ending in 9 cents', function () {
    seedFixtureMarketplace();

    $products = Product::query()->with('offers')->get();

    expect($products)->each(function ($product) {
        $product->offers->count()->toBeBetween(2, 5);
        $product->offers->each(fn ($offer) => $offer->price_cents
            ->toBeLessThanOrEqual($product->value->retail_price_cents)
            ->toBeGreaterThanOrEqual(99)
            ->and($offer->value->price_cents % 10)->toBe(9));
    });
});

it('stocks offers with 10 keys except for sold-out offers and leaves some offers inactive', function () {
    seedFixtureMarketplace();

    $offers = Offer::query()
        ->withCount([
            'keys as available_keys' => fn ($query) => $query->where('status', ProductKeyStatus::Available),
            'keys as sold_keys' => fn ($query) => $query->where('status', ProductKeyStatus::Sold),
        ])
        ->get();
    $soldOutOffers = $offers->where('available_keys', 0);

    expect($offers->pluck('available_keys')->unique()->sort()->values()->all())->toBe([0, CatalogSeeder::KEYS_PER_OFFER])
        ->and($soldOutOffers)->not->toBeEmpty()
        ->and($soldOutOffers->every(fn (Offer $offer): bool => $offer->sold_keys > 0))->toBeTrue()
        ->and($offers->where('is_active', false))->not->toBeEmpty()
        ->and(Product::query()->whereNull('min_price_cents')->exists())->toBeTrue();
});

it('derives sold_count from real paid orders so no product stats drift', function () {
    seedFixtureMarketplace();

    $soldKeys = DB::table('product_keys')->where('status', ProductKeyStatus::Sold->value)->count();

    expect($soldKeys)->toBeGreaterThan(0)
        ->and((int) Product::query()->sum('sold_count'))->toBe($soldKeys)
        ->and(app(ProductStockAggregator::class)->drift())->toBe([]);
});

it('creates a demo buyer who can sign in and has paid orders with keys', function () {
    seedFixtureMarketplace();

    $demoUser = User::query()->where('email', OrderHistorySeeder::DEMO_EMAIL)->sole();

    expect(Hash::check(OrderHistorySeeder::DEMO_PASSWORD, $demoUser->password))->toBeTrue()
        ->and($demoUser->orders()->where('status', 'paid')->count())->toBe(OrderHistorySeeder::DEMO_ORDER_COUNT)
        ->and(DB::table('product_keys')
            ->join('order_items', 'order_items.id', '=', 'product_keys.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $demoUser->id)
            ->exists())->toBeTrue();
});

it('seeds the homepage layout and banners that link to in-stock products', function () {
    seedFixtureMarketplace();

    expect(HomepageBlock::query()->orderBy('position')->pluck('type')->all())->toBe([
        'hero_banner',
        'best_sellers',
        'deals',
        'new_releases',
        'genre_rail',
    ]);

    $banners = Banner::query()->with('product')->get();

    expect($banners)->not->toBeEmpty()
        ->and($banners->every(fn (Banner $banner): bool => $banner->product?->min_price_cents !== null))->toBeTrue();
});

it('does not seed a database that already has a catalog', function () {
    seedFixtureMarketplace();
    $productCount = Product::query()->count();

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
        ->expectsOutputToContain('Catalog already seeded; skipping.')
        ->assertSuccessful();

    expect(Product::query()->count())->toBe($productCount);
});

it('skips seeding while another process holds the seeding lock', function () {
    config(['database.connections.other_instance' => config('database.connections.pgsql')]);
    $otherInstance = DB::connection('other_instance');
    $otherInstance->beginTransaction();
    $otherInstance->select('SELECT pg_advisory_xact_lock(?)', [DatabaseSeeder::ADVISORY_LOCK_KEY]);

    try {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])
            ->expectsOutputToContain('Another process is seeding the database; skipping.')
            ->assertSuccessful();

        expect(Product::query()->exists())->toBeFalse();
    } finally {
        $otherInstance->rollBack();
        DB::purge('other_instance');
    }
});
