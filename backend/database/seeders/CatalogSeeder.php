<?php

namespace Database\Seeders;

use App\Casts\PostgresTextArray;
use App\Enums\GamePlatform;
use App\Enums\ProductKeyStatus;
use App\Services\Search\GameSearchText;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\InsertsInChunks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Games from the committed Steam snapshot, plus products (one per real
 * platform x 1-3 regions), 2-5 seller offers per product and their keys.
 *
 * Availability mix, so the UI has real edge cases to render:
 *  - ~2% of products: every offer sold out (product shows "no offers");
 *  - ~5% of offers overall: sold out (0 available keys, sales history only);
 *  - ~3% of offers: inactive (seller paused the listing; keys kept);
 *  - every other offer: exactly KEYS_PER_OFFER available keys.
 *
 * Sold keys are created by OrderHistorySeeder as extra rows.
 */
class CatalogSeeder extends Seeder
{
    use InsertsInChunks;

    public const KEYS_PER_OFFER = 10;

    public const SOLD_OUT_OFFER_SHARE = 0.05;

    public const INACTIVE_OFFER_SHARE = 0.03;

    public const UNAVAILABLE_PRODUCT_SHARE = 0.02;

    private const RANDOM_SEED = 2027;

    /**
     * Price factors relative to the Steam (PC, EU) retail price.
     *
     * @var array<string, float>
     */
    private const PLATFORM_PRICE_FACTOR = ['pc' => 1.0, 'playstation' => 1.06, 'xbox' => 0.96, 'nintendo-switch' => 1.1];

    /**
     * @var array<string, float>
     */
    private const REGION_PRICE_FACTOR = ['global' => 1.0, 'europe' => 0.97, 'north-america' => 1.02, 'united-kingdom' => 0.98, 'latam' => 0.85];

    public function __construct(private ?string $gamesPath = null) {}

    public function run(): void
    {
        $random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $now = CarbonImmutable::now();
        $games = json_decode(File::get($this->gamesPath ?? database_path('data/games.json')), true, flags: JSON_THROW_ON_ERROR);

        $gameIds = $this->seedGamesAndGenres($games, $now);
        $products = $this->seedProducts($games, $gameIds, $random, $now);
        $offers = $this->seedOffers($products, $random, $now);
        $this->seedKeys($offers, $products, $random, $now);
    }

    /**
     * @param  list<array<string, mixed>>  $games
     * @return array<int, int> steam_app_id => game id
     */
    private function seedGamesAndGenres(array $games, CarbonImmutable $now): array
    {
        $genreNames = array_values(array_unique(array_merge(...array_column($games, 'genres'))));
        $this->insertInChunks('genres', array_map(
            fn (string $name): array => ['name' => $name, 'slug' => Str::slug($name), 'created_at' => $now, 'updated_at' => $now],
            $genreNames,
        ));

        $this->insertInChunks('games', array_map(fn (array $game): array => [
            'steam_app_id' => $game['steam_app_id'],
            'name' => $game['name'],
            'slug' => $game['slug'],
            // Bulk inserts skip model events, so derive the search columns here.
            'search_name' => GameSearchText::normalize($game['name']),
            'search_tokens' => PostgresTextArray::toLiteral(GameSearchText::tokens($game['name'])),
            'short_description' => $game['short_description'],
            'description' => $game['description'],
            'cover_image_url' => $game['cover_image_url'],
            'header_image_url' => $game['header_image_url'],
            'hero_image_url' => $game['hero_image_url'],
            'screenshots' => json_encode($game['screenshots'], JSON_UNESCAPED_SLASHES),
            'released_at' => $game['released_at'],
            'metacritic' => $game['metacritic'],
            'developer' => $game['developer'],
            'publisher' => $game['publisher'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $games), rowsPerStatement: 100);

        $gameIds = DB::table('games')->pluck('id', 'steam_app_id')->all();
        $genreIds = DB::table('genres')->pluck('id', 'name')->all();

        $gameGenreRows = [];
        foreach ($games as $game) {
            foreach ($game['genres'] as $genreName) {
                $gameGenreRows[] = ['game_id' => $gameIds[$game['steam_app_id']], 'genre_id' => $genreIds[$genreName]];
            }
        }
        $this->insertInChunks('game_genre', $gameGenreRows);

        return $gameIds;
    }

    /**
     * One product per real platform x 1-3 regions.
     *
     * @param  list<array<string, mixed>>  $games
     * @param  array<int, int>  $gameIds
     * @return array<int, array{id: int, base_price_cents: int, retail_price_cents: int}> keyed by product id
     */
    private function seedProducts(array $games, array $gameIds, Randomizer $random, CarbonImmutable $now): array
    {
        $platformIds = DB::table('platforms')->pluck('id', 'slug')->all();
        $regionIds = DB::table('regions')->pluck('id', 'slug')->all();

        $rows = [];
        $pricing = [];

        foreach ($games as $game) {
            $discountedFraction = $this->discountedFraction($game['released_at'], $random, $now);

            foreach ($game['platforms'] as $platformSlug) {
                $platform = GamePlatform::from($platformSlug);

                foreach ($this->pickRegions($platform, $random) as $regionSlug) {
                    $name = sprintf('%s %s Key %s', $game['name'], $platform->activationService(), RegionSeeder::REGIONS[$regionSlug]);
                    $slug = Str::slug($name);
                    $retailPriceCents = (int) $game['retail_price_cents'];

                    $rows[] = [
                        'game_id' => $gameIds[$game['steam_app_id']],
                        'platform_id' => $platformIds[$platformSlug],
                        'region_id' => $regionIds[$regionSlug],
                        'name' => $name,
                        'slug' => $slug,
                        'retail_price_cents' => $retailPriceCents,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $pricing[$slug] = [
                        'retail_price_cents' => $retailPriceCents,
                        'base_price_cents' => (int) round($retailPriceCents * $discountedFraction
                            * self::PLATFORM_PRICE_FACTOR[$platformSlug] * self::REGION_PRICE_FACTOR[$regionSlug]),
                    ];
                }
            }
        }

        $this->insertInChunks('products', $rows);

        $products = [];
        foreach (DB::table('products')->get(['id', 'slug']) as $product) {
            $products[$product->id] = ['id' => $product->id] + $pricing[$product->slug];
        }

        return $products;
    }

    /**
     * 2-5 offers from distinct sellers, priced around the product's base price.
     *
     * @param  array<int, array{id: int, base_price_cents: int, retail_price_cents: int}>  $products
     * @return list<array{id: int, product_id: int}>
     */
    private function seedOffers(array $products, Randomizer $random, CarbonImmutable $now): array
    {
        $sellerIds = DB::table('sellers')->orderBy('id')->pluck('id')->all();
        $rows = [];

        foreach ($products as $product) {
            $offerCount = $random->getInt(2, min(5, count($sellerIds)));

            foreach ((array) $random->pickArrayKeys($sellerIds, $offerCount) as $sellerIndex) {
                $rows[] = [
                    'product_id' => $product['id'],
                    'seller_id' => $sellerIds[$sellerIndex],
                    'price_cents' => $this->offerPrice($product, $random),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertInChunks('offers', $rows);

        return DB::table('offers')->orderBy('id')->get(['id', 'product_id'])
            ->map(fn (object $offer): array => ['id' => $offer->id, 'product_id' => $offer->product_id])
            ->all();
    }

    /**
     * Decide each offer's availability, then give in-stock offers their keys.
     *
     * @param  list<array{id: int, product_id: int}>  $offers
     * @param  array<int, array{id: int, base_price_cents: int, retail_price_cents: int}>  $products
     */
    private function seedKeys(array $offers, array $products, Randomizer $random, CarbonImmutable $now): void
    {
        $offerCount = count($offers);
        $soldOutOfferIds = [];

        // A few products where every offer is sold out.
        $unavailableProductCount = max(1, (int) round(count($products) * self::UNAVAILABLE_PRODUCT_SHARE));
        $unavailableProductIds = array_flip((array) $random->pickArrayKeys($products, min($unavailableProductCount, count($products))));

        foreach ($offers as $offer) {
            if (isset($unavailableProductIds[$offer['product_id']])) {
                $soldOutOfferIds[$offer['id']] = true;
            }
        }

        // Top up sold-out offers elsewhere, then pause a few listings.
        $candidateOffers = $random->shuffleArray(array_values(array_filter(
            $offers,
            fn (array $offer): bool => ! isset($soldOutOfferIds[$offer['id']]),
        )));

        $extraSoldOut = max(1, (int) round($offerCount * self::SOLD_OUT_OFFER_SHARE) - count($soldOutOfferIds));
        foreach (array_splice($candidateOffers, 0, $extraSoldOut) as $offer) {
            $soldOutOfferIds[$offer['id']] = true;
        }

        $inactiveOfferIds = array_column(array_splice($candidateOffers, 0, max(1, (int) round($offerCount * self::INACTIVE_OFFER_SHARE))), 'id');
        DB::table('offers')->whereIn('id', $inactiveOfferIds)->update(['is_active' => false]);

        $keyRows = [];
        foreach ($offers as $offer) {
            if (isset($soldOutOfferIds[$offer['id']])) {
                continue;
            }

            for ($i = 0; $i < self::KEYS_PER_OFFER; $i++) {
                $keyRows[] = [
                    'offer_id' => $offer['id'],
                    'code' => Crypt::encryptString($this->randomKeyCode($random)),
                    'status' => ProductKeyStatus::Available->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertInChunks('product_keys', $keyRows, rowsPerStatement: 1000);
    }

    /**
     * Key-shop prices fall with a game's age: share of retail price paid.
     */
    private function discountedFraction(?string $releasedAt, Randomizer $random, CarbonImmutable $now): float
    {
        $ageInYears = $releasedAt === null ? 2.0 : CarbonImmutable::parse($releasedAt)->diffInYears($now);

        return match (true) {
            $ageInYears >= 3 => $this->randomFloat($random, 0.30, 0.65),
            $ageInYears >= 1 => $this->randomFloat($random, 0.55, 0.85),
            default => $this->randomFloat($random, 0.75, 1.0),
        };
    }

    /**
     * @return list<string> region slugs
     */
    private function pickRegions(GamePlatform $platform, Randomizer $random): array
    {
        [$baseRegion, $extraRegions] = match ($platform) {
            GamePlatform::Pc => $random->getInt(0, 1) === 0
                ? ['global', ['europe', 'north-america', 'united-kingdom', 'latam']]
                : ['europe', ['global', 'north-america', 'united-kingdom', 'latam']],
            GamePlatform::PlayStation, GamePlatform::Xbox => ['europe', ['north-america', 'united-kingdom', 'latam']],
            GamePlatform::NintendoSwitch => ['europe', ['north-america']],
        };

        $extraCount = $random->getInt(0, min(2, count($extraRegions)));
        $extras = $extraCount === 0 ? [] : array_map(
            fn (int $index): string => $extraRegions[$index],
            (array) $random->pickArrayKeys($extraRegions, $extraCount),
        );

        return [$baseRegion, ...$extras];
    }

    /**
     * Base price +/-8%, ending in 9 cents, never above retail, at least 0.99.
     *
     * @param  array{base_price_cents: int, retail_price_cents: int}  $product
     */
    private function offerPrice(array $product, Randomizer $random): int
    {
        $cents = (int) round($product['base_price_cents'] * $this->randomFloat($random, 0.92, 1.08));
        $cents = intdiv($cents, 10) * 10 + 9;

        return max(99, min($cents, $product['retail_price_cents']));
    }
}
