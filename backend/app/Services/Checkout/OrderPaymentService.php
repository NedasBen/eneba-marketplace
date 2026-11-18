<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductKeyStatus;
use App\Models\Order;
use App\Models\ProductKey;
use App\Models\User;
use App\Services\Catalog\ProductStockAggregator;
use App\Services\Payments\CardDetails;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pays a pending order (docs/ARCHITECTURE.md "Checkout & key delivery").
 *
 * Everything runs in one transaction holding the order's row lock, so two
 * payment attempts for one order run one after another and an order is
 * never charged twice. Before charging, the order's reserved keys are
 * locked and counted: if a lapsed hold was taken by another buyer, the
 * count is short and the order expires without a charge. A late payment
 * can therefore never take keys someone else has claimed.
 *
 * The simulated gateway answers instantly, so charging under the lock is
 * fine here. A real provider would use payment intents and a webhook.
 */
final class OrderPaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ReleaseExpiredReservations $expiry,
        private readonly ProductStockAggregator $stats,
    ) {}

    public function pay(User $user, string $reference, CardDetails $card): PaymentOutcome
    {
        return DB::transaction(function () use ($user, $reference, $card): PaymentOutcome {
            $order = Order::query()->where('user_id', $user->id)->where('reference', $reference)->lockForUpdate()->first();

            if ($order === null) {
                throw new NotFoundHttpException('Order not found.');
            }

            if ($order->status === OrderStatus::Paid) {
                return PaymentOutcome::paid($order);
            }

            if ($order->status === OrderStatus::Expired) {
                return PaymentOutcome::expired($order);
            }

            $items = $order->items()->get();
            $needed = (int) $items->sum('quantity');

            $keyIds = ProductKey::query()
                ->whereIn('order_item_id', $items->pluck('id'))
                ->where('status', ProductKeyStatus::Reserved)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if ($order->expires_at->isPast() || count($keyIds) !== $needed) {
                $this->stats->refresh($this->expiry->expire([$order->id]));

                return PaymentOutcome::expired($order->refresh());
            }

            $result = $this->gateway->charge($order->total_cents, $card, $order->reference);

            $order->payments()->create([
                'provider' => $this->gateway->name(),
                'provider_reference' => $result->providerReference,
                'status' => $result->succeeded ? PaymentStatus::Succeeded : PaymentStatus::Declined,
                'failure_code' => $result->failureCode,
                'amount_cents' => $order->total_cents,
                'card_last4' => $card->lastFour(),
            ]);

            if (! $result->succeeded) {
                return PaymentOutcome::declined($order, (string) $result->failureCode);
            }

            $sold = ProductKey::query()->whereKey($keyIds)->where('status', ProductKeyStatus::Reserved)->update([
                'status' => ProductKeyStatus::Sold,
                'reserved_until' => null,
                'updated_at' => now(),
            ]);

            if ($sold !== $needed) {
                // Unreachable while the keys are locked above; rolls back (including the payment row) if it ever happens.
                throw new RuntimeException("Order {$order->reference}: expected {$needed} reserved keys, marked {$sold} sold.");
            }

            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

            // The bought lines leave the cart; anything else the user added stays.
            $user->cartItems()->whereIn('offer_id', $items->pluck('offer_id'))->delete();

            $this->stats->refresh($items->pluck('product_id')->unique()->values()->all());

            return PaymentOutcome::paid($order);
        });
    }
}
