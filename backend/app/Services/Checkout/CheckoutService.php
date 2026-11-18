<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\ProductKeyStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ProductKey;
use App\Models\User;
use App\Services\Catalog\ProductStockAggregator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the server cart into a pending order with its keys reserved
 * (docs/ARCHITECTURE.md "Checkout & key delivery").
 *
 * - Idempotent per (user, Idempotency-Key): a repeat returns the same order.
 * - Prices, names and sellers are snapshotted from the current offers.
 * - Keys are picked with FOR UPDATE SKIP LOCKED: two buyers racing for the
 *   last key never block each other or get the same key; one gets a 409.
 * - All or nothing: any short line rolls everything back.
 */
final class CheckoutService
{
    public const RESERVATION_MINUTES = 15;

    public function __construct(
        private readonly ReleaseExpiredReservations $expiry,
        private readonly ProductStockAggregator $stats,
    ) {}

    /**
     * @return array{0: Order, 1: bool} the order and whether it was created now
     *
     * @throws ValidationException when the cart is empty
     * @throws InsufficientStockException when a line can't be fully reserved
     */
    public function checkout(User $user, string $idempotencyKey): array
    {
        $this->expiry->run();

        return DB::transaction(function () use ($user, $idempotencyKey): array {
            // Same per-user lock as the cart: a checkout and a cart change (or
            // two checkouts) for one user run one after another.
            User::query()->whereKey($user->id)->lockForUpdate()->value('id');

            $existing = Order::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return [$existing, false];
            }

            /** @var Collection<int, CartItem> $lines */
            $lines = $user->cartItems()->with(['offer.product:id,name', 'offer.seller:id,name'])->orderBy('offer_id')->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            // One checkout at a time per user: an earlier pending order (another
            // tab, an abandoned payment) gives its keys back first, so a user
            // can't hold twice the stock. Undone too if this checkout fails.
            $releasedProducts = $this->expiry->expire(
                Order::query()->where('user_id', $user->id)->where('status', OrderStatus::Pending)->lockForUpdate()->pluck('id')->all(),
            );

            $picked = [];
            $shortages = [];

            foreach ($lines as $line) {
                $keyIds = $line->offer->is_active ? $this->lockAvailableKeys($line->offer_id, $line->quantity) : [];

                if (count($keyIds) < $line->quantity) {
                    $shortages[] = [
                        'offer_id' => $line->offer_id,
                        'product_name' => $line->offer->product->name,
                        'seller_name' => $line->offer->seller->name,
                        'requested' => $line->quantity,
                        'available' => count($keyIds),
                    ];
                }

                $picked[$line->id] = $keyIds;
            }

            if ($shortages !== []) {
                throw new InsufficientStockException($shortages);
            }

            $expiresAt = now()->addMinutes(self::RESERVATION_MINUTES);

            $order = Order::query()->create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'total_cents' => (int) $lines->sum(fn (CartItem $line): int => $line->offer->price_cents * $line->quantity),
                'idempotency_key' => $idempotencyKey,
                'expires_at' => $expiresAt,
            ]);

            foreach ($lines as $line) {
                $item = $order->items()->create([
                    'offer_id' => $line->offer_id,
                    'product_id' => $line->offer->product_id,
                    'product_name' => $line->offer->product->name,
                    'seller_name' => $line->offer->seller->name,
                    'unit_price_cents' => $line->offer->price_cents,
                    'quantity' => $line->quantity,
                ]);

                ProductKey::query()->whereKey($picked[$line->id])->update([
                    'status' => ProductKeyStatus::Reserved,
                    'reserved_until' => $expiresAt,
                    'order_item_id' => $item->id,
                    'updated_at' => now(),
                ]);
            }

            $this->stats->refresh(array_values(array_unique([
                ...$lines->map(fn (CartItem $line): int => $line->offer->product_id)->all(),
                ...$releasedProducts,
            ])));

            return [$order, true];
        });
    }

    /**
     * Lock up to `$quantity` in-stock keys of an offer, skipping keys another
     * transaction holds. Expired holds count as in stock (lazy expiry).
     *
     * @return list<int>
     */
    private function lockAvailableKeys(int $offerId, int $quantity): array
    {
        $inStock = ProductKey::inStockSql();

        $rows = DB::select(
            "SELECT id FROM product_keys WHERE offer_id = ? AND {$inStock} ORDER BY id LIMIT ? FOR UPDATE SKIP LOCKED",
            [$offerId, $quantity],
        );

        return array_map(fn (object $row): int => (int) $row->id, $rows);
    }
}
