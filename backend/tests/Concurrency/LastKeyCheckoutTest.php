<?php

use App\Enums\OrderStatus;
use App\Enums\ProductKeyStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ProductKey;
use App\Models\User;
use App\Services\Checkout\CheckoutService;
use App\Services\Checkout\InsufficientStockException;
use App\Services\Checkout\OrderPaymentService;
use App\Services\Payments\CardDetails;
use App\Services\Payments\TestCards;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Real lock contention between two Postgres connections (two sessions),
 * not mocks. Connection B runs a checkout inside a transaction it keeps
 * open, so its row locks are held while connection A runs. lock_timeout
 * turns an accidental lock wait into a failure instead of a hung test.
 */

const SECOND_CONNECTION = 'pgsql_concurrent';

beforeEach(function () {
    config()->set('database.connections.'.SECOND_CONNECTION, config('database.connections.pgsql'));

    foreach (['pgsql', SECOND_CONNECTION] as $connection) {
        DB::connection($connection)->statement("SET lock_timeout = '3s'");
    }
});

afterEach(function () {
    $second = DB::connection(SECOND_CONNECTION);

    while ($second->transactionLevel() > 0) {
        $second->rollBack();
    }

    DB::setDefaultConnection('pgsql');
    DB::purge(SECOND_CONNECTION);
});

/**
 * Run `$callback` with connection B as the default connection, inside a
 * transaction on B that is left open (its locks stay held) until committed.
 */
function onSecondConnectionUncommitted(Closure $callback): mixed
{
    DB::setDefaultConnection(SECOND_CONNECTION);
    DB::connection(SECOND_CONNECTION)->beginTransaction();

    try {
        return $callback();
    } finally {
        DB::setDefaultConnection('pgsql');
    }
}

/**
 * Two buyers with the same single-key offer in their carts.
 *
 * @return array{0: User, 1: User}
 */
function twoBuyersForTheLastKey(): array
{
    $offer = stockedOffer(stock: 1);
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    CartItem::factory()->for($alice)->for($offer)->create(['quantity' => 1]);
    CartItem::factory()->for($bob)->for($offer)->create(['quantity' => 1]);

    return [$alice, $bob];
}

it('gives the last key to exactly one of two concurrent checkouts, without blocking the other', function () {
    [$alice, $bob] = twoBuyersForTheLastKey();
    $checkout = app(CheckoutService::class);

    // B: Alice's checkout locks and reserves the key, but hasn't committed yet.
    [$aliceOrder] = onSecondConnectionUncommitted(
        fn () => $checkout->checkout(User::query()->findOrFail($alice->id), (string) Str::uuid()),
    );

    // A: Bob's checkout skips the locked key instead of waiting for it.
    $started = microtime(true);
    $bobsAttempt = fn () => $checkout->checkout(User::query()->findOrFail($bob->id), (string) Str::uuid());

    expect($bobsAttempt)->toThrow(InsufficientStockException::class)
        ->and(microtime(true) - $started)->toBeLessThan(2.0);

    DB::connection(SECOND_CONNECTION)->commit();

    $key = ProductKey::query()->sole();
    expect($key->status)->toBe(ProductKeyStatus::Reserved)
        ->and($key->orderItem->order_id)->toBe($aliceOrder->id)
        ->and(Order::query()->pluck('user_id')->all())->toBe([$alice->id])
        ->and($bob->cartItems()->count())->toBe(1);
});

it('lets the other buyer have the key when the first checkout rolls back', function () {
    [$alice, $bob] = twoBuyersForTheLastKey();
    $checkout = app(CheckoutService::class);

    onSecondConnectionUncommitted(fn () => $checkout->checkout(User::query()->findOrFail($alice->id), (string) Str::uuid()));
    expect(fn () => $checkout->checkout(User::query()->findOrFail($bob->id), (string) Str::uuid()))->toThrow(InsufficientStockException::class);

    // Alice's transaction fails (e.g. the connection drops): nothing she reserved survives.
    DB::connection(SECOND_CONNECTION)->rollBack();

    [$bobsOrder] = $checkout->checkout(User::query()->findOrFail($bob->id), (string) Str::uuid());

    expect(ProductKey::query()->sole()->orderItem->order_id)->toBe($bobsOrder->id)
        ->and(Order::query()->pluck('user_id')->all())->toBe([$bob->id]);
});

it('does not hand a lapsed key to a new buyer while its late payment is in progress', function () {
    [$alice, $bob] = twoBuyersForTheLastKey();
    $checkout = app(CheckoutService::class);
    [$aliceOrder] = $checkout->checkout(User::query()->findOrFail($alice->id), (string) Str::uuid());

    // Alice's payment starts a moment before the deadline and holds her key locked.
    $this->travel(14)->minutes();
    $card = new CardDetails(TestCards::SUCCESS, 12, now()->addYear()->year, '123', 'Alice');
    $outcome = onSecondConnectionUncommitted(
        fn () => app(OrderPaymentService::class)->pay(User::query()->findOrFail($alice->id), $aliceOrder->reference, $card),
    );

    // The deadline passes while the payment is still open: Bob's checkout must skip the locked key.
    $this->travel(2)->minutes();
    expect(fn () => $checkout->checkout(User::query()->findOrFail($bob->id), (string) Str::uuid()))->toThrow(InsufficientStockException::class);

    DB::connection(SECOND_CONNECTION)->commit();

    expect($outcome->status)->toBe('paid')
        ->and(ProductKey::query()->sole()->status)->toBe(ProductKeyStatus::Sold)
        ->and(Order::query()->find($aliceOrder->id)->status)->toBe(OrderStatus::Paid)
        ->and(Order::query()->where('user_id', $bob->id)->count())->toBe(0);
});
