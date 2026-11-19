<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductKeyStatus;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductKey;
use App\Models\User;
use App\Services\Checkout\ReleaseExpiredReservations;
use App\Services\Payments\TestCards;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function addToCart(User $user, Offer $offer, int $quantity = 1): CartItem
{
    return CartItem::factory()->for($user)->for($offer)->create(['quantity' => $quantity]);
}

function checkout(?string $idempotencyKey = null): TestResponse
{
    return test()->postJson('/api/v1/checkout', [], ['Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid()]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function pay(string $reference, string $cardNumber = TestCards::SUCCESS, array $overrides = []): TestResponse
{
    return test()->postJson("/api/v1/orders/{$reference}/pay", [
        'card_number' => $cardNumber,
        'expiry_month' => 12,
        'expiry_year' => now()->addYears(2)->year,
        'cvc' => '123',
        'cardholder_name' => 'Demo Buyer',
        ...$overrides,
    ]);
}

/**
 * @return array<string, int>
 */
function keyStatuses(Offer $offer): array
{
    return ProductKey::query()->where('offer_id', $offer->id)->get()->countBy(fn (ProductKey $key): string => $key->status->value)->all();
}

describe('the normal flow', function () {
    it('checks out, pays, reveals the keys, clears the bought lines and updates the product stats', function () {
        $this->freezeTime();
        $user = signedIn();
        $offer = stockedOffer(stock: 3, priceCents: 1500);
        $other = stockedOffer();
        addToCart($user, $offer, 2);

        $order = checkout()
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total.amount_cents', 3000)
            ->assertJsonPath('data.expires_at', now()->addMinutes(15)->toIso8601String())
            ->assertJsonPath('data.items.0.product_name', $offer->product->name)
            ->assertJsonPath('data.items.0.seller_name', $offer->seller->name)
            ->assertJsonPath('data.items.0.unit_price.amount_cents', 1500)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonMissingPath('data.items.0.keys')
            ->json('data');

        $reserved = ProductKey::query()->where('offer_id', $offer->id)->where('status', ProductKeyStatus::Reserved)->get();
        expect($reserved)->toHaveCount(2)
            ->and($reserved->every(fn (ProductKey $key): bool => $key->reserved_until->toIso8601String() === now()->addMinutes(15)->toIso8601String()))->toBeTrue()
            ->and($offer->product->fresh()->offers_count)->toBe(1);

        // Something else in the cart that wasn't bought stays there.
        addToCart($user, $other);

        $paid = pay($order['reference'], '4242 4242 4242 4242')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonCount(2, 'data.items.0.keys')
            ->json('data');

        $sold = ProductKey::query()->where('offer_id', $offer->id)->where('status', ProductKeyStatus::Sold)->orderBy('id')->get();
        expect(collect($paid['items'][0]['keys'])->pluck('code')->all())->toBe($sold->pluck('code')->all())
            ->and($sold)->toHaveCount(2)
            ->and($user->cartItems()->pluck('offer_id')->all())->toBe([$other->id])
            ->and($offer->product->fresh()->sold_count)->toBe(2);

        $payment = Payment::query()->sole();
        expect($payment)->status->toBe(PaymentStatus::Succeeded)->amount_cents->toBe(3000)->card_last4->toBe('4242')->provider->toBe('simulated')
            ->and(json_encode($payment->getAttributes()))->not->toContain(TestCards::SUCCESS)->not->toContain('"123"');
    });

    it('snapshots prices at checkout, so a later price change does not alter the order', function () {
        $user = signedIn();
        $offer = stockedOffer(priceCents: 1000);
        addToCart($user, $offer, 2);
        $reference = checkout()->assertCreated()->json('data.reference');

        $offer->update(['price_cents' => 9999]);

        pay($reference)->assertOk()->assertJsonPath('data.total.amount_cents', 2000)->assertJsonPath('data.items.0.unit_price.amount_cents', 1000);
        expect(Payment::query()->sole()->amount_cents)->toBe(2000);
    });

    it('shows the last key as sold out while reserved, and back in stock once the hold is released', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 1, priceCents: 1234);
        addToCart($user, $offer);
        $this->artisan('catalog:recalculate-product-stats');

        checkout()->assertCreated();
        expect($offer->product->fresh())->offers_count->toBe(0)->min_price_cents->toBeNull();

        $this->travel(16)->minutes();
        $this->artisan('orders:release-expired')->expectsOutput('Expired 1 order.')->assertSuccessful();

        expect($offer->product->fresh())->offers_count->toBe(1)->min_price_cents->toBe(1234)
            ->and(keyStatuses($offer))->toBe(['available' => 1]);
    });
});

describe('checkout', function () {
    it('requires authentication', function (string $method, string $uri) {
        $this->json($method, $uri)->assertUnauthorized();
    })->with([
        ['POST', '/api/v1/checkout'],
        ['GET', '/api/v1/orders/01JD0000000000000000000000'],
        ['POST', '/api/v1/orders/01JD0000000000000000000000/pay'],
    ]);

    it('refuses an empty cart', function () {
        signedIn();

        checkout()->assertUnprocessable()->assertJsonValidationErrors(['cart' => 'Your cart is empty.']);
    });

    it('requires a UUID Idempotency-Key header', function (?string $key) {
        $user = signedIn();
        addToCart($user, stockedOffer());

        $this->postJson('/api/v1/checkout', [], $key === null ? [] : ['Idempotency-Key' => $key])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');
        expect(Order::query()->count())->toBe(0);
    })->with(['missing' => [null], 'not a uuid' => ['abc']]);

    it('reports short lines with a 409 and reserves nothing', function () {
        $user = signedIn();
        $plenty = stockedOffer(stock: 5);
        $scarce = stockedOffer(stock: 1);
        addToCart($user, $plenty, 2);
        // Stock dropped after the line was added (the cart allows that, with a notice).
        addToCart($user, $scarce, 3);
        $this->artisan('catalog:recalculate-product-stats');
        $statsBefore = $scarce->product->fresh()->only(['min_price_cents', 'offers_count', 'sold_count']);

        checkout()
            ->assertConflict()
            ->assertJsonPath('message', 'Some items in your cart are no longer available in the quantity you chose.')
            ->assertJsonPath('shortages', [[
                'offer_id' => $scarce->id,
                'product_name' => $scarce->product->name,
                'seller_name' => $scarce->seller->name,
                'requested' => 3,
                'available' => 1,
            ]]);

        expect(Order::query()->count())->toBe(0)
            ->and(keyStatuses($plenty))->toBe(['available' => 5])
            ->and(keyStatuses($scarce))->toBe(['available' => 1])
            ->and($user->cartItems()->count())->toBe(2)
            ->and($scarce->product->fresh()->only(['min_price_cents', 'offers_count', 'sold_count']))->toBe($statsBefore);
    });

    it('treats a paused offer as a shortage', function () {
        $user = signedIn();
        $paused = stockedOffer(stock: 5, attributes: ['is_active' => false]);
        addToCart($user, $paused);

        checkout()->assertConflict()->assertJsonPath('shortages.0.offer_id', $paused->id)->assertJsonPath('shortages.0.available', 0);
    });

    it('creates one order for a repeated Idempotency-Key', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 5);
        addToCart($user, $offer, 2);
        $key = (string) Str::uuid();

        $first = checkout($key)->assertCreated()->json('data.reference');
        $repeat = checkout($key)->assertOk()->json('data.reference');
        // Different letter case, same key.
        checkout(strtoupper($key))->assertOk()->assertJsonPath('data.reference', $first);

        expect($repeat)->toBe($first)
            ->and(Order::query()->count())->toBe(1)
            ->and(keyStatuses($offer))->toBe(['available' => 3, 'reserved' => 2]);
    });

    it('releases an earlier pending order when the user checks out again', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 3);
        addToCart($user, $offer, 2);

        $earlier = checkout()->assertCreated()->json('data.reference');
        $later = checkout()->assertCreated()->json('data.reference');

        expect(Order::query()->firstWhere('reference', $earlier)->status)->toBe(OrderStatus::Expired)
            ->and(keyStatuses($offer))->toBe(['available' => 1, 'reserved' => 2]);

        pay($earlier)->assertStatus(410);
        pay($later)->assertOk();
    });

    it('reuses keys whose hold has lapsed even before the sweep has run', function () {
        $offer = stockedOffer(stock: 1);
        $first = signedIn();
        addToCart($first, $offer);
        checkout()->assertCreated();

        $this->travel(16)->minutes();
        $second = signedIn();
        addToCart($second, $offer);

        checkout()->assertCreated();
        expect(ProductKey::query()->sole()->orderItem->order->user_id)->toBe($second->id);
    });
});

describe('paying', function () {
    it('keeps the order pending after a decline and accepts a retry', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 2);
        addToCart($user, $offer);
        $reference = checkout()->json('data.reference');

        pay($reference, TestCards::CARD_DECLINED)
            ->assertStatus(402)
            ->assertJsonPath('failure_code', 'card_declined')
            ->assertJsonPath('message', 'Your card was declined. Try another card.');
        pay($reference, TestCards::INSUFFICIENT_FUNDS)->assertStatus(402)->assertJsonPath('failure_code', 'insufficient_funds');

        expect(Order::query()->sole()->status)->toBe(OrderStatus::Pending)
            ->and(keyStatuses($offer))->toBe(['available' => 1, 'reserved' => 1])
            ->and(Payment::query()->pluck('status')->all())->toBe([PaymentStatus::Declined, PaymentStatus::Declined])
            ->and($user->cartItems()->count())->toBe(1);

        pay($reference)->assertOk()->assertJsonPath('data.status', 'paid');
        expect(keyStatuses($offer))->toBe(['available' => 1, 'sold' => 1]);
    });

    it('charges once when the same order is paid twice', function () {
        $user = signedIn();
        addToCart($user, stockedOffer());
        $reference = checkout()->json('data.reference');

        $first = pay($reference)->assertOk()->json('data.items.0.keys');
        $second = pay($reference)->assertOk()->json('data.items.0.keys');

        expect($second)->toBe($first)->and(Payment::query()->count())->toBe(1);
    });

    it('returns 410 for an expired order and releases its keys without charging', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 2);
        addToCart($user, $offer, 2);
        $reference = checkout()->json('data.reference');

        $this->travel(15 * 60 + 1)->seconds();

        $this->getJson("/api/v1/orders/{$reference}")->assertOk()->assertJsonPath('data.status', 'expired');
        pay($reference)->assertStatus(410)->assertJsonPath('message', 'This order has expired and its keys were released. Go back to your cart to check out again.');

        expect(Order::query()->sole()->status)->toBe(OrderStatus::Expired)
            ->and(keyStatuses($offer))->toBe(['available' => 2])
            ->and(Payment::query()->count())->toBe(0);
    });

    it('never lets a late payment take keys another buyer has claimed', function () {
        $offer = stockedOffer(stock: 1);
        $late = signedIn();
        addToCart($late, $offer);
        $lateReference = checkout()->json('data.reference');

        $this->travel(16)->minutes();
        $buyer = signedIn();
        addToCart($buyer, $offer);
        $buyerReference = checkout()->assertCreated()->json('data.reference');

        signedIn($late);
        pay($lateReference)->assertStatus(410);

        $key = ProductKey::query()->sole();
        expect($key->status)->toBe(ProductKeyStatus::Reserved)
            ->and($key->orderItem->order->reference)->toBe($buyerReference)
            ->and(Payment::query()->count())->toBe(0);

        signedIn($buyer);
        pay($buyerReference)->assertOk();
    });

    it('refuses to charge when the keys are gone even if the deadline check passes', function () {
        $offer = stockedOffer(stock: 1);
        $late = signedIn();
        addToCart($late, $offer);
        $lateReference = checkout()->json('data.reference');

        $this->travel(16)->minutes();
        $buyer = signedIn();
        addToCart($buyer, $offer);
        checkout()->assertCreated();

        // A clock or data glitch makes the old order look current again.
        Order::query()->where('reference', $lateReference)->update(['expires_at' => now()->addMinutes(10)]);

        signedIn($late);
        pay($lateReference)->assertStatus(410);

        expect(Payment::query()->count())->toBe(0)
            ->and(Order::query()->firstWhere('reference', $lateReference)->status)->toBe(OrderStatus::Expired)
            ->and(ProductKey::query()->sole()->orderItem->order->user_id)->toBe($buyer->id);
    });

    it('validates the card', function (array $overrides, string $field, string $message) {
        $user = signedIn();
        addToCart($user, stockedOffer());
        $reference = checkout()->json('data.reference');

        pay($reference, $overrides['card_number'] ?? TestCards::SUCCESS, $overrides)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);
        expect(Payment::query()->count())->toBe(0);
    })->with([
        'typo in the number' => [['card_number' => '4242424242424241'], 'card_number', 'This card number is not valid.'],
        'a real-looking card' => [['card_number' => '4111111111111111'], 'card_number', 'use one of the test cards'],
        'expired last month' => [['expiry_month' => now()->subMonth()->month, 'expiry_year' => now()->subMonth()->year], 'expiry_month', 'This card has expired.'],
        'month out of range' => [['expiry_month' => 13], 'expiry_month', 'between'],
        'short cvc' => [['cvc' => '12'], 'cvc', 'The CVC must be 3 digits.'],
        'no name' => [['cardholder_name' => '  '], 'cardholder_name', 'required'],
    ]);
});

describe('order ownership and visibility', function () {
    it('does not let a user view or pay another user\'s order', function () {
        $owner = signedIn();
        addToCart($owner, stockedOffer());
        $reference = checkout()->json('data.reference');

        signedIn();
        $this->getJson("/api/v1/orders/{$reference}")->assertNotFound()->assertJsonPath('message', 'Order not found.');
        pay($reference)->assertNotFound()->assertJsonPath('message', 'Order not found.');

        expect(Payment::query()->count())->toBe(0)->and(Order::query()->sole()->status)->toBe(OrderStatus::Pending);
    });

    it('shows keys only once the order is paid', function () {
        $user = signedIn();
        addToCart($user, stockedOffer());
        $reference = checkout()->json('data.reference');

        $this->getJson("/api/v1/orders/{$reference}")->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonMissingPath('data.items.0.keys');
        pay($reference)->assertOk();
        $this->getJson("/api/v1/orders/{$reference}")->assertOk()->assertJsonCount(1, 'data.items.0.keys');
    });
});

describe('the expiry sweep', function () {
    it('expires overdue orders, frees their keys and leaves keys re-reserved by another order alone', function () {
        $offer = stockedOffer(stock: 2);
        $first = signedIn();
        addToCart($first, $offer, 2);
        checkout()->assertCreated();

        $this->travel(16)->minutes();
        // Another buyer takes one of the lapsed keys before the sweep runs.
        $second = signedIn();
        addToCart($second, $offer);
        $secondReference = checkout()->assertCreated()->json('data.reference');

        // Checkout already swept the first order on the way in.
        expect(Order::query()->where('user_id', $first->id)->sole()->status)->toBe(OrderStatus::Expired)
            ->and(keyStatuses($offer))->toBe(['available' => 1, 'reserved' => 1])
            ->and(ProductKey::query()->where('status', ProductKeyStatus::Reserved)->sole()->orderItem->order->reference)->toBe($secondReference);
    });

    it('leaves orders that are still within their deadline alone', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 1);
        addToCart($user, $offer);
        checkout()->assertCreated();

        $this->travel(14)->minutes();
        $this->artisan('orders:release-expired')->expectsOutput('Expired 0 orders.');

        expect(Order::query()->sole()->status)->toBe(OrderStatus::Pending)->and(keyStatuses($offer))->toBe(['reserved' => 1]);
    });

    it('runs on catalog reads at most once a minute', function () {
        $this->withoutDefer();
        $user = signedIn();
        addToCart($user, stockedOffer(stock: 2));
        $firstReference = checkout()->json('data.reference');
        $status = fn (string $reference): OrderStatus => Order::query()->firstWhere('reference', $reference)->status;

        $this->travel(16)->minutes();
        $this->getJson('/api/v1/catalog/filters')->assertOk();
        expect($status($firstReference))->toBe(OrderStatus::Expired);

        // Another overdue order: the next read within the minute doesn't sweep again.
        $other = User::factory()->create();
        $overdue = Order::factory()->for($other)->create(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/catalog/filters')->assertOk();
        expect($overdue->fresh()->status)->toBe(OrderStatus::Pending);

        $this->travel(61)->seconds();
        $this->getJson('/api/v1/catalog/filters')->assertOk();
        expect($overdue->fresh()->status)->toBe(OrderStatus::Expired);
    });

    it('does not sweep after a failed catalog read', function () {
        $overdue = Order::factory()->for(User::factory())->create(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/v1/products?q='.str_repeat('a', 101))->assertUnprocessable();
        expect($overdue->fresh()->status)->toBe(OrderStatus::Pending);

        $this->getJson('/api/v1/catalog/filters')->assertOk();
        expect($overdue->fresh()->status)->toBe(OrderStatus::Expired);
    });

    it('drains a backlog larger than one batch from the command', function () {
        $total = ReleaseExpiredReservations::BATCH_SIZE + 2;
        Order::factory()->for(User::factory())->count($total)->create(['expires_at' => now()->subMinute()]);

        $this->artisan('orders:release-expired')->expectsOutput("Expired {$total} orders.")->assertSuccessful();

        expect(Order::query()->where('status', OrderStatus::Pending)->exists())->toBeFalse();
    });
});

describe('payment rate limit', function () {
    it('allows 10 payment attempts a minute per user, then answers 429', function () {
        $user = signedIn();
        addToCart($user, stockedOffer(stock: 1));
        $reference = checkout()->assertCreated()->json('data.reference');

        foreach (range(1, 10) as $attempt) {
            pay($reference, TestCards::CARD_DECLINED)->assertPaymentRequired();
        }

        pay($reference)->assertTooManyRequests()->assertHeader('Retry-After');
        expect(Order::query()->sole()->status)->toBe(OrderStatus::Pending);

        // The limit is per user: someone else can still pay.
        $other = signedIn();
        addToCart($other, stockedOffer(stock: 1));
        pay(checkout()->assertCreated()->json('data.reference'))->assertOk();

        $this->travel(61)->seconds();
        signedIn($user);
        pay($reference)->assertOk();
    });
});
