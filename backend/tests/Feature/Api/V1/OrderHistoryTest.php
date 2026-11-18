<?php

use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * An order for `$user` created `$minutesAgo` minutes ago, with one line per entry of `$quantities`.
 *
 * @param  list<int>  $quantities
 * @param  array<string, mixed>  $attributes
 */
function pastOrder(User $user, int $minutesAgo, array $quantities = [1], array $attributes = []): Order
{
    $order = Order::factory()->for($user)->paid()->create([
        'created_at' => now()->subMinutes($minutesAgo),
        'expires_at' => now()->subMinutes($minutesAgo)->addMinutes(15),
        ...$attributes,
    ]);

    foreach ($quantities as $index => $quantity) {
        $offer = Offer::factory()->for(catalogProduct())->create();
        OrderItem::factory()->for($order)->for($offer)->create([
            'product_name' => "Game {$order->id}-{$index}",
            'quantity' => $quantity,
        ]);
    }

    return $order;
}

it('requires authentication', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
});

it('lists only the signed-in user\'s orders, newest first', function () {
    $user = signedIn();
    $older = pastOrder($user, minutesAgo: 120);
    $newer = pastOrder($user, minutesAgo: 5);
    pastOrder(User::factory()->create(), minutesAgo: 1);

    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.*.reference', [$newer->reference, $older->reference])
        ->assertJsonPath('meta.total', 2);
});

it('summarises each order without revealing keys', function () {
    $user = signedIn();
    $order = pastOrder($user, minutesAgo: 10, quantities: [2, 1, 3, 1], attributes: ['total_cents' => 4321]);

    $row = $this->getJson('/api/v1/orders')->assertOk()->json('data.0');

    expect($row)->toMatchArray([
        'reference' => $order->reference,
        'status' => 'paid',
        'total' => ['amount_cents' => 4321, 'currency' => 'EUR'],
        'item_count' => 7,
        'product_names' => ["Game {$order->id}-0", "Game {$order->id}-1", "Game {$order->id}-2"],
        'more_products' => 1,
    ])
        ->and($row['cover_image_url'])->toBeString()
        ->and(json_encode($row))->not->toContain('"keys"')->not->toContain('"code"');
});

it('reports a pending order past its deadline as expired', function () {
    $user = signedIn();
    Order::factory()->for($user)->create(['expires_at' => now()->subMinute()]);
    Order::factory()->for($user)->create(['expires_at' => now()->addMinutes(10), 'created_at' => now()->subSecond()]);

    $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.*.status', ['expired', 'pending']);
});

it('paginates ten orders per page', function () {
    $user = signedIn();
    foreach (range(1, 12) as $minutesAgo) {
        Order::factory()->for($user)->paid()->create(['created_at' => now()->subMinutes($minutesAgo)]);
    }

    $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/v1/orders?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.current_page', 2);
});

it('returns an empty list for a user without orders', function () {
    signedIn();

    $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
});

it('uses the same number of queries however many orders are on the page', function () {
    $user = signedIn();
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/orders')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    pastOrder($user, minutesAgo: 1, quantities: [1]);
    $forOne = $countQueries();

    foreach (range(2, 9) as $minutesAgo) {
        pastOrder($user, minutesAgo: $minutesAgo, quantities: [1, 2]);
    }

    expect($countQueries())->toBe($forOne);
});
