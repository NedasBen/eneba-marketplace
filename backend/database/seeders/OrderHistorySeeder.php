<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductKeyStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\InsertsInChunks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Past paid orders, so best sellers and sold_count come from real sales.
 *
 * Every sold-out offer gets at least one sale (that is why it is sold out);
 * the remaining orders favour well-reviewed, recent PC/EU products. Sold keys
 * are extra rows, so in-stock offers keep all of their available keys.
 */
class OrderHistorySeeder extends Seeder
{
    use InsertsInChunks;

    public const DEMO_EMAIL = 'demo@example.com';

    public const DEMO_PASSWORD = 'password';

    public const DEMO_ORDER_COUNT = 5;

    public const ORDERS_PER_PRODUCT = 2;

    private const RANDOM_SEED = 2028;

    private const HISTORY_DAYS = 90;

    private const BUYER_NAMES = [
        'Aistė Kazlauskienė', 'Jonas Petrauskas', 'Emma Schneider', 'Lukas Nowak', 'Sofia Rossi',
        'Mateo García', 'Olivia Johnson', 'Noah Williams', 'Mia Jansen', 'Elias Lindqvist',
        'Chloé Martin', 'Tomás Novák', 'Hanna Kowalska', 'Leon Müller', 'Ieva Ozola',
        'Oskar Berg', 'Laura Fernández', 'Daniel Popescu', 'Zeynep Yılmaz', 'Marta Silva',
    ];

    private const PLATFORM_DEMAND = ['pc' => 1.0, 'playstation' => 0.7, 'xbox' => 0.5, 'nintendo-switch' => 0.45];

    private const REGION_DEMAND = ['global' => 1.0, 'europe' => 1.0, 'united-kingdom' => 0.5, 'north-america' => 0.4, 'latam' => 0.3];

    public function run(): void
    {
        $random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $now = CarbonImmutable::now();

        [$demoUserId, $buyerIds] = $this->seedUsers($now);
        $catalog = $this->loadCatalog($now);

        if ($catalog['offers'] === []) {
            return;
        }

        $orders = [];

        // 1. Each sold-out offer sold out through real sales.
        foreach ($catalog['sold_out_offer_ids'] as $offerId) {
            $orders[] = $this->makeOrder($buyerIds[$random->getInt(0, count($buyerIds) - 1)], [[$offerId, $random->getInt(1, 3)]], $catalog, $random, $now);
        }

        // 2. Weighted organic demand, with a few recent orders for the demo account.
        $organicOrderCount = max(10, count($catalog['product_weights']) * self::ORDERS_PER_PRODUCT) - count($orders);

        for ($i = 0; $i < $organicOrderCount; $i++) {
            $userId = $i < self::DEMO_ORDER_COUNT ? $demoUserId : $buyerIds[$random->getInt(0, count($buyerIds) - 1)];
            $orders[] = $this->makeOrder($userId, $this->pickLines($catalog, $random), $catalog, $random, $now, recent: $i < self::DEMO_ORDER_COUNT);
        }

        $this->persist($orders, $random, $now);
    }

    /**
     * @return array{0: int, 1: list<int>}
     */
    private function seedUsers(CarbonImmutable $now): array
    {
        // Hash once: bcrypt is deliberately slow, and buyers never log in.
        $buyerPasswordHash = Hash::make(Str::random(40));

        $rows = [[
            'name' => 'Demo Buyer',
            'email' => self::DEMO_EMAIL,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]];

        foreach (self::BUYER_NAMES as $index => $name) {
            $rows[] = [
                'name' => $name,
                'email' => sprintf('buyer%02d@example.com', $index + 1),
                'password' => $buyerPasswordHash,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('users')->insert($rows);
        $userIds = DB::table('users')->pluck('id', 'email')->all();

        return [
            $userIds[self::DEMO_EMAIL],
            array_values(array_diff_key($userIds, [self::DEMO_EMAIL => true])),
        ];
    }

    /**
     * @return array{
     *     offers: array<int, object>,
     *     offers_by_product: array<int, list<int>>,
     *     product_weights: array<int, float>,
     *     sold_out_offer_ids: list<int>
     * }
     */
    private function loadCatalog(CarbonImmutable $now): array
    {
        $rows = DB::table('offers')
            ->join('products', 'products.id', '=', 'offers.product_id')
            ->join('games', 'games.id', '=', 'products.game_id')
            ->join('platforms', 'platforms.id', '=', 'products.platform_id')
            ->join('regions', 'regions.id', '=', 'products.region_id')
            ->join('sellers', 'sellers.id', '=', 'offers.seller_id')
            ->orderBy('offers.id')
            ->get([
                'offers.id', 'offers.product_id', 'offers.price_cents',
                'products.name as product_name', 'sellers.name as seller_name',
                'games.metacritic', 'games.released_at',
                'platforms.slug as platform_slug', 'regions.slug as region_slug',
            ]);

        $offersWithKeys = DB::table('product_keys')->distinct()->pluck('offer_id')->flip()->all();

        $catalog = ['offers' => [], 'offers_by_product' => [], 'product_weights' => [], 'sold_out_offer_ids' => []];

        foreach ($rows as $offer) {
            $catalog['offers'][$offer->id] = $offer;
            $catalog['offers_by_product'][$offer->product_id][] = $offer->id;

            if (! isset($offersWithKeys[$offer->id])) {
                $catalog['sold_out_offer_ids'][] = $offer->id;
            }

            $releasedAt = $offer->released_at === null ? null : CarbonImmutable::parse($offer->released_at);
            $recency = match (true) {
                $releasedAt === null => 1.0,
                $releasedAt->greaterThan($now->subYear()) => 2.0,
                $releasedAt->greaterThan($now->subYears(3)) => 1.3,
                default => 1.0,
            };

            $catalog['product_weights'][$offer->product_id] = (max(($offer->metacritic ?? 72) - 50, 5) ** 2)
                * $recency
                * self::PLATFORM_DEMAND[$offer->platform_slug]
                * self::REGION_DEMAND[$offer->region_slug];
        }

        return $catalog;
    }

    /**
     * 1-3 lines from distinct products; cheaper offers sell more often.
     *
     * @param  array{offers: array<int, object>, offers_by_product: array<int, list<int>>, product_weights: array<int, float>}  $catalog
     * @return list<array{0: int, 1: int}> [offer id, quantity]
     */
    private function pickLines(array $catalog, Randomizer $random): array
    {
        $lineCount = min($this->weightedPick($random, [1 => 70, 2 => 22, 3 => 8]), count($catalog['product_weights']));
        $lines = [];

        while (count($lines) < $lineCount) {
            $productId = $this->weightedPick($random, $catalog['product_weights']);

            if (isset($lines[$productId])) {
                continue;
            }

            $offerIds = $catalog['offers_by_product'][$productId];
            usort($offerIds, fn (int $a, int $b): int => $catalog['offers'][$a]->price_cents <=> $catalog['offers'][$b]->price_cents);
            $offerWeights = [];
            foreach ($offerIds as $rank => $offerId) {
                $offerWeights[$offerId] = [3, 2][$rank] ?? 1;
            }

            $lines[$productId] = [$this->weightedPick($random, $offerWeights), $random->getInt(1, 100) <= 85 ? 1 : 2];
        }

        return array_values($lines);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $lines
     * @param  array{offers: array<int, object>}  $catalog
     * @return array<string, mixed>
     */
    private function makeOrder(int $userId, array $lines, array $catalog, Randomizer $random, CarbonImmutable $now, bool $recent = false): array
    {
        $latestRelease = max(array_map(
            fn (array $line): int => $catalog['offers'][$line[0]]->released_at === null ? 0 : CarbonImmutable::parse($catalog['offers'][$line[0]]->released_at)->getTimestamp(),
            $lines,
        ));
        $earliest = max($now->subDays($recent ? 14 : self::HISTORY_DAYS)->getTimestamp(), $latestRelease + 86_400);
        $paidAt = CarbonImmutable::createFromTimestamp($random->getInt(min($earliest, $now->getTimestamp() - 3600), $now->getTimestamp() - 3600));
        $createdAt = $paidAt->subSeconds($random->getInt(60, 600));

        $items = array_map(fn (array $line): array => [
            'offer_id' => $line[0],
            'product_id' => $catalog['offers'][$line[0]]->product_id,
            'product_name' => $catalog['offers'][$line[0]]->product_name,
            'seller_name' => $catalog['offers'][$line[0]]->seller_name,
            'unit_price_cents' => $catalog['offers'][$line[0]]->price_cents,
            'quantity' => $line[1],
        ], $lines);

        return [
            'reference' => Str::lower((string) Str::ulid($createdAt)),
            'user_id' => $userId,
            'total_cents' => array_sum(array_map(fn (array $item): int => $item['unit_price_cents'] * $item['quantity'], $items)),
            'created_at' => $createdAt,
            'paid_at' => $paidAt,
            'items' => $items,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     */
    private function persist(array $orders, Randomizer $random, CarbonImmutable $now): void
    {
        $this->insertInChunks('orders', array_map(fn (array $order): array => [
            'reference' => $order['reference'],
            'user_id' => $order['user_id'],
            'status' => OrderStatus::Paid->value,
            'total_cents' => $order['total_cents'],
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => $order['created_at']->addMinutes(15),
            'paid_at' => $order['paid_at'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['paid_at'],
        ], $orders));

        $orderIds = DB::table('orders')->pluck('id', 'reference')->all();

        $itemRows = [];
        $paymentRows = [];
        foreach ($orders as $order) {
            $orderId = $orderIds[$order['reference']];

            foreach ($order['items'] as $item) {
                $itemRows[] = ['order_id' => $orderId] + $item + ['created_at' => $order['created_at'], 'updated_at' => $order['created_at']];
            }

            $paymentRows[] = [
                'order_id' => $orderId,
                'provider' => 'simulated',
                'provider_reference' => 'sim_'.bin2hex($random->getBytes(8)),
                'status' => PaymentStatus::Succeeded->value,
                'amount_cents' => $order['total_cents'],
                'card_last4' => '4242',
                'created_at' => $order['paid_at'],
                'updated_at' => $order['paid_at'],
            ];
        }

        $this->insertInChunks('order_items', $itemRows);
        $this->insertInChunks('payments', $paymentRows);

        $keyRows = [];
        foreach (DB::table('order_items')->get(['id', 'offer_id', 'quantity', 'created_at']) as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $keyRows[] = [
                    'offer_id' => $item->offer_id,
                    'code' => Crypt::encryptString($this->randomKeyCode($random)),
                    'status' => ProductKeyStatus::Sold->value,
                    'order_item_id' => $item->id,
                    'created_at' => $item->created_at,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertInChunks('product_keys', $keyRows, rowsPerStatement: 1000);
    }
}
