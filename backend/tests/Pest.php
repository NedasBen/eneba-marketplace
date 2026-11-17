<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
 * Raw catalog row builders. Temporary: replaced by model factories once the
 * Eloquent models exist (seeder step).
 */

/**
 * Insert a product (with its own game, platform and region) and return its id.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertCatalogProduct(array $overrides = []): int
{
    $suffix = Str::lower(Str::random(6));
    $now = now();

    $gameId = DB::table('games')->insertGetId([
        'steam_app_id' => random_int(1, 9_999_999),
        'name' => "Game {$suffix}",
        'slug' => "game-{$suffix}",
        'short_description' => 'Short.',
        'description' => 'Long.',
        'cover_image_url' => 'https://cdn.example/cover.jpg',
        'header_image_url' => 'https://cdn.example/header.jpg',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $platformId = DB::table('platforms')->insertGetId(['name' => 'PC', 'slug' => "pc-{$suffix}", 'activation_service' => 'Steam', 'created_at' => $now, 'updated_at' => $now]);
    $regionId = DB::table('regions')->insertGetId(['name' => 'GLOBAL', 'slug' => "global-{$suffix}", 'created_at' => $now, 'updated_at' => $now]);

    return DB::table('products')->insertGetId(array_replace([
        'game_id' => $gameId,
        'platform_id' => $platformId,
        'region_id' => $regionId,
        'name' => "Game {$suffix} Steam Key GLOBAL",
        'slug' => "game-{$suffix}-steam-key-global",
        'retail_price_cents' => 5999,
        'created_at' => $now,
        'updated_at' => $now,
    ], $overrides));
}

function insertOffer(int $productId, int $priceCents, bool $isActive = true): int
{
    $suffix = Str::lower(Str::random(6));
    $now = now();

    $sellerId = DB::table('sellers')->insertGetId([
        'name' => "Seller {$suffix}",
        'slug' => "seller-{$suffix}",
        'rating' => 98.5,
        'country_code' => 'LT',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return DB::table('offers')->insertGetId([
        'product_id' => $productId,
        'seller_id' => $sellerId,
        'price_cents' => $priceCents,
        'is_active' => $isActive,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

/**
 * Insert keys for an offer. Reserved and sold keys are attached to a fresh
 * order line, as the product_keys state constraint requires.
 */
function insertKeys(int $offerId, string $status, int $count = 1, ?DateTimeInterface $reservedUntil = null): void
{
    $now = now();
    $orderItemId = null;

    if ($status !== 'available') {
        $offer = DB::table('offers')->find($offerId);
        $orderId = DB::table('orders')->insertGetId([
            'reference' => (string) Str::ulid(),
            'user_id' => User::factory()->create()->id,
            'status' => $status === 'sold' ? 'paid' : 'pending',
            'total_cents' => $offer->price_cents * $count,
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => $reservedUntil ?? $now->copy()->addMinutes(15),
            'paid_at' => $status === 'sold' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $orderItemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'offer_id' => $offerId,
            'product_id' => $offer->product_id,
            'product_name' => 'Product',
            'seller_name' => 'Seller',
            'unit_price_cents' => $offer->price_cents,
            'quantity' => $count,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    DB::table('product_keys')->insert(array_map(fn (): array => [
        'offer_id' => $offerId,
        'code' => Crypt::encryptString(Str::upper(Str::random(15))),
        'status' => $status,
        'reserved_until' => $status === 'reserved' ? ($reservedUntil ?? $now->copy()->addMinutes(15)) : null,
        'order_item_id' => $orderItemId,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $count)));
}

/**
 * @return object{min_price_cents: int|null, offers_count: int, sold_count: int}
 */
function productStats(int $productId): object
{
    return DB::table('products')->select('min_price_cents', 'offers_count', 'sold_count')->find($productId);
}
