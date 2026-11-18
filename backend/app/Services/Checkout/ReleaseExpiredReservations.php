<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\ProductKeyStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductKey;
use App\Services\Catalog\ProductStockAggregator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The tidy-up half of reservation expiry (docs/ARCHITECTURE.md
 * "Releasing expired reservations without cron"). Correctness never depends
 * on it: checkout already treats an expired hold as free stock and payment
 * refuses an expired order. The sweep just makes stored state match:
 * pending orders past expires_at → expired, their keys → available, and the
 * product stats recomputed, all in one transaction.
 */
final class ReleaseExpiredReservations
{
    /** Orders handled per run; a backlog is cleared over a few runs. */
    public const BATCH_SIZE = 500;

    public const THROTTLE_KEY = 'reservations:sweep';

    public function __construct(private readonly ProductStockAggregator $stats) {}

    /**
     * Expire every pending order past its deadline. Orders locked by a
     * payment in progress are skipped and picked up next time.
     *
     * @return int number of orders expired
     */
    public function run(): int
    {
        return DB::transaction(function (): int {
            $orderIds = Order::query()
                ->where('status', OrderStatus::Pending)
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->lock('for update skip locked')
                ->pluck('id')
                ->all();

            $this->stats->refresh($this->expire($orderIds));

            return count($orderIds);
        });
    }

    /**
     * Run at most once per `$seconds` (catalog reads call this).
     */
    public function runThrottled(int $seconds = 60): void
    {
        if (Cache::add(self::THROTTLE_KEY, true, $seconds)) {
            $this->run();
        }
    }

    /**
     * Mark the given orders expired and free their keys. The caller must
     * hold the order rows' locks inside its transaction and refresh the
     * returned products' stats in that same transaction.
     *
     * Only keys still reserved for these orders' items are released: a key
     * another checkout already re-reserved (after the hold lapsed) points at
     * that checkout's item and is left alone.
     *
     * @param  list<int>  $orderIds
     * @return list<int> affected product ids
     */
    public function expire(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        Order::query()->whereKey($orderIds)->update(['status' => OrderStatus::Expired, 'updated_at' => now()]);

        $items = OrderItem::query()->whereIn('order_id', $orderIds)->get(['id', 'product_id']);

        ProductKey::query()
            ->whereIn('order_item_id', $items->pluck('id'))
            ->where('status', ProductKeyStatus::Reserved)
            ->update(['status' => ProductKeyStatus::Available, 'reserved_until' => null, 'order_item_id' => null, 'updated_at' => now()]);

        return $items->pluck('product_id')->unique()->values()->all();
    }
}
