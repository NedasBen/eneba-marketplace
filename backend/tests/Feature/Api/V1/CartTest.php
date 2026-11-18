<?php

use App\Models\CartItem;
use App\Models\CartMerge;
use App\Models\ProductKey;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @param  list<array{offer_id: int, quantity: int}>  $items
 */
function mergePayload(array $items, ?string $mergeId = null): array
{
    return ['merge_id' => $mergeId ?? (string) Str::uuid(), 'items' => $items];
}

describe('authentication', function () {
    it('requires a token for every cart endpoint', function (string $method, string $uri) {
        $this->json($method, $uri)->assertUnauthorized();
    })->with([
        ['GET', '/api/v1/cart'],
        ['POST', '/api/v1/cart/items'],
        ['PATCH', '/api/v1/cart/items/1'],
        ['DELETE', '/api/v1/cart/items/1'],
        ['POST', '/api/v1/cart/merge'],
    ]);
});

describe('reading the cart', function () {
    it('returns lines with the current price and stock, never the price saved when added', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 3, priceCents: 1500);
        CartItem::factory()->for($user)->for($offer)->create(['quantity' => 2, 'price_seen_cents' => 1200]);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.offer_id', $offer->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.stock', 3)
            ->assertJsonPath('data.items.0.max_quantity', 3)
            ->assertJsonPath('data.items.0.unit_price.amount_cents', 1500)
            ->assertJsonPath('data.items.0.price_seen.amount_cents', 1200)
            ->assertJsonPath('data.items.0.line_total.amount_cents', 3000)
            ->assertJsonPath('data.items.0.product.name', $offer->product->name)
            ->assertJsonPath('data.items.0.seller.name', $offer->seller->name)
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.total.amount_cents', 3000);
    });

    it('keeps lines that went inactive or short of stock but leaves them out of the total', function () {
        $user = signedIn();
        $fine = stockedOffer(stock: 5, priceCents: 1000);
        $paused = stockedOffer(stock: 5, priceCents: 2000, attributes: ['is_active' => false]);
        $short = stockedOffer(stock: 1, priceCents: 3000);
        CartItem::factory()->for($user)->for($fine)->create(['quantity' => 1]);
        CartItem::factory()->for($user)->for($paused)->create(['quantity' => 1]);
        CartItem::factory()->for($user)->for($short)->create(['quantity' => 2]);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.1.is_active', false)
            ->assertJsonPath('data.items.1.max_quantity', 0)
            ->assertJsonPath('data.items.2.stock', 1)
            ->assertJsonPath('data.item_count', 4)
            ->assertJsonPath('data.total.amount_cents', 1000);
    });

    it('only shows the signed-in user\'s lines', function () {
        CartItem::factory()->for(User::factory())->for(stockedOffer())->create();
        signedIn();

        $this->getJson('/api/v1/cart')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.total.amount_cents', 0);
    });
});

describe('adding items', function () {
    it('adds a line priced from the offer, ignoring any price sent by the client', function () {
        $user = signedIn();
        $offer = stockedOffer(priceCents: 2499);

        $this->postJson('/api/v1/cart/items', ['offer_id' => $offer->id, 'quantity' => 2, 'price_cents' => 1, 'unit_price' => ['amount_cents' => 1]])
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price.amount_cents', 2499)
            ->assertJsonPath('data.total.amount_cents', 4998);

        expect($user->cartItems()->sole())->quantity->toBe(2)->price_seen_cents->toBe(2499);
    });

    it('adds to an existing line and refreshes the price it was seen at', function () {
        $user = signedIn();
        $offer = stockedOffer(priceCents: 1800);
        CartItem::factory()->for($user)->for($offer)->create(['quantity' => 3, 'price_seen_cents' => 1500]);

        $this->postJson('/api/v1/cart/items', ['offer_id' => $offer->id])->assertOk()->assertJsonPath('data.items.0.quantity', 4);

        expect($user->cartItems()->sole())->quantity->toBe(4)->price_seen_cents->toBe(1800);
    });

    it('caps a line at 10 keys', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 50);
        CartItem::factory()->for($user)->for($offer)->create(['quantity' => 9]);

        $this->postJson('/api/v1/cart/items', ['offer_id' => $offer->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'at most 10']);

        $this->postJson('/api/v1/cart/items', ['offer_id' => $offer->id, 'quantity' => 11])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        expect($user->cartItems()->sole()->quantity)->toBe(9);
    });

    it('caps a line at the keys in stock', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 3);
        CartItem::factory()->for($user)->for($offer)->create(['quantity' => 2]);

        $this->postJson('/api/v1/cart/items', ['offer_id' => $offer->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Only 3 keys are available from this seller. You already have 2 in your cart.']);
    });

    it('counts expired reservations as stock but not sold or held keys', function () {
        signedIn();
        $offer = stockedOffer(stock: 1);
        ProductKey::factory()->for($offer)->reserved(now()->subMinute())->create();
        ProductKey::factory()->for($offer)->reserved(now()->addMinutes(10))->create();
        ProductKey::factory()->for($offer)->sold()->create();

        $this->postJson('/api/v1/cart/items', ['offer_id' => $offer->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/cart/items', ['offer_id' => $offer->id])->assertUnprocessable();
    });

    it('rejects offers that cannot be bought', function (Closure $makeOffer, string $message) {
        signedIn();

        $this->postJson('/api/v1/cart/items', ['offer_id' => $makeOffer()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offer_id' => $message]);
    })->with([
        'unknown' => [fn () => 999999, 'This offer is no longer available.'],
        'inactive' => [fn () => stockedOffer(attributes: ['is_active' => false])->id, 'This offer is no longer available.'],
        'sold out' => [fn () => stockedOffer(stock: 0)->id, 'This offer is out of stock.'],
    ]);

    it('validates the payload', function (array $payload, string $field) {
        signedIn();

        $this->postJson('/api/v1/cart/items', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no offer' => [[], 'offer_id'],
        'offer not a number' => [['offer_id' => 'abc'], 'offer_id'],
        'zero quantity' => [['offer_id' => 1, 'quantity' => 0], 'quantity'],
        'negative quantity' => [['offer_id' => 1, 'quantity' => -3], 'quantity'],
    ]);
});

describe('changing and removing lines', function () {
    it('sets a quantity within the caps', function () {
        $user = signedIn();
        $line = CartItem::factory()->for($user)->for(stockedOffer(stock: 4))->create(['quantity' => 1]);

        $this->patchJson("/api/v1/cart/items/{$line->id}", ['quantity' => 4])->assertOk()->assertJsonPath('data.items.0.quantity', 4);
        $this->patchJson("/api/v1/cart/items/{$line->id}", ['quantity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Only 4 keys are available from this seller.']);
        $this->patchJson("/api/v1/cart/items/{$line->id}", ['quantity' => 0])->assertUnprocessable();

        expect($line->fresh()->quantity)->toBe(4);
    });

    it('lets a line be lowered to the stock that is left', function () {
        $user = signedIn();
        $line = CartItem::factory()->for($user)->for(stockedOffer(stock: 2))->create(['quantity' => 5]);

        $this->patchJson("/api/v1/cart/items/{$line->id}", ['quantity' => 2])->assertOk()->assertJsonPath('data.total.amount_cents', 3998);
    });

    it('refuses to change a line whose offer is no longer sold, but still allows removing it', function () {
        $user = signedIn();
        $line = CartItem::factory()->for($user)->for(stockedOffer(attributes: ['is_active' => false]))->create(['quantity' => 2]);

        $this->patchJson("/api/v1/cart/items/{$line->id}", ['quantity' => 1])->assertUnprocessable();
        $this->deleteJson("/api/v1/cart/items/{$line->id}")->assertOk()->assertJsonCount(0, 'data.items');
        $this->assertModelMissing($line);
    });

    it('returns 404 for a missing line', function () {
        signedIn();

        $this->patchJson('/api/v1/cart/items/999999', ['quantity' => 1])->assertNotFound()->assertJsonPath('message', 'Cart item not found.');
        $this->deleteJson('/api/v1/cart/items/999999')->assertNotFound();
    });

    it('does not let a user read, change or remove another user\'s line', function () {
        $alice = User::factory()->create();
        $alicesLine = CartItem::factory()->for($alice)->for(stockedOffer())->create(['quantity' => 2]);
        signedIn();

        // Same 404 as a missing id, so line ids can't be probed.
        $this->patchJson("/api/v1/cart/items/{$alicesLine->id}", ['quantity' => 5])->assertNotFound()->assertJsonPath('message', 'Cart item not found.');
        $this->deleteJson("/api/v1/cart/items/{$alicesLine->id}")->assertNotFound();
        $this->getJson('/api/v1/cart')->assertJsonCount(0, 'data.items');

        expect($alicesLine->fresh())->not->toBeNull()->quantity->toBe(2);
    });
});

describe('merging the guest cart', function () {
    it('adds guest lines to the cart, priced from the offers', function () {
        $user = signedIn();
        $first = stockedOffer(priceCents: 1000);
        $second = stockedOffer(priceCents: 2000);

        $this->postJson('/api/v1/cart/merge', mergePayload([
            ['offer_id' => $first->id, 'quantity' => 1, 'price' => 1],
            ['offer_id' => $second->id, 'quantity' => 2, 'unit_price' => ['amount_cents' => 1]],
        ]))
            ->assertOk()
            ->assertJsonPath('adjustments', [])
            ->assertJsonPath('data.items.*.offer_id', [$first->id, $second->id])
            ->assertJsonPath('data.total.amount_cents', 5000);

        expect($user->cartItems()->pluck('price_seen_cents')->all())->toEqualCanonicalizing([1000, 2000]);
    });

    it('sums with existing lines', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 20);
        CartItem::factory()->for($user)->for($offer)->create(['quantity' => 3]);

        $this->postJson('/api/v1/cart/merge', mergePayload([['offer_id' => $offer->id, 'quantity' => 4]]))
            ->assertOk()
            ->assertJsonPath('adjustments', [])
            ->assertJsonPath('data.items.0.quantity', 7);
    });

    it('clamps a sum to 10 keys and to the stock, and reports it', function () {
        $user = signedIn();
        $plenty = stockedOffer(stock: 50);
        $scarce = stockedOffer(stock: 4);
        CartItem::factory()->for($user)->for($plenty)->create(['quantity' => 8]);
        CartItem::factory()->for($user)->for($scarce)->create(['quantity' => 1]);

        $this->postJson('/api/v1/cart/merge', mergePayload([
            ['offer_id' => $plenty->id, 'quantity' => 5],
            ['offer_id' => $scarce->id, 'quantity' => 6],
        ]))
            ->assertOk()
            ->assertJsonPath('adjustments', [
                ['offer_id' => $plenty->id, 'kind' => 'clamped', 'requested' => 5, 'quantity' => 10],
                ['offer_id' => $scarce->id, 'kind' => 'clamped', 'requested' => 6, 'quantity' => 4],
            ])
            ->assertJsonPath('data.items.*.quantity', [10, 4]);
    });

    it('skips unknown, inactive and sold-out offers and reports them', function () {
        $user = signedIn();
        $good = stockedOffer();
        $paused = stockedOffer(attributes: ['is_active' => false]);
        $soldOut = stockedOffer(stock: 0);

        $this->postJson('/api/v1/cart/merge', mergePayload([
            ['offer_id' => 999999, 'quantity' => 1],
            ['offer_id' => $good->id, 'quantity' => 1],
            ['offer_id' => $paused->id, 'quantity' => 2],
            ['offer_id' => $soldOut->id, 'quantity' => 3],
        ]))
            ->assertOk()
            ->assertJsonPath('adjustments', [
                ['offer_id' => 999999, 'kind' => 'unavailable', 'requested' => 1, 'quantity' => 0],
                ['offer_id' => $paused->id, 'kind' => 'unavailable', 'requested' => 2, 'quantity' => 0],
                ['offer_id' => $soldOut->id, 'kind' => 'out_of_stock', 'requested' => 3, 'quantity' => 0],
            ])
            ->assertJsonPath('data.items.*.offer_id', [$good->id]);

        expect($user->cartItems()->count())->toBe(1);
    });

    it('never lowers an existing line, even when its stock dropped', function () {
        $user = signedIn();
        $offer = stockedOffer(stock: 2);
        CartItem::factory()->for($user)->for($offer)->create(['quantity' => 5]);

        $this->postJson('/api/v1/cart/merge', mergePayload([['offer_id' => $offer->id, 'quantity' => 1]]))
            ->assertOk()
            ->assertJsonPath('adjustments.0', ['offer_id' => $offer->id, 'kind' => 'clamped', 'requested' => 1, 'quantity' => 5])
            ->assertJsonPath('data.items.0.quantity', 5);
    });

    it('combines the same offer repeated in one payload', function () {
        signedIn();
        $offer = stockedOffer(stock: 20);

        $this->postJson('/api/v1/cart/merge', mergePayload([
            ['offer_id' => $offer->id, 'quantity' => 6],
            ['offer_id' => $offer->id, 'quantity' => 7],
        ]))
            ->assertOk()
            ->assertJsonPath('adjustments.0', ['offer_id' => $offer->id, 'kind' => 'clamped', 'requested' => 13, 'quantity' => 10])
            ->assertJsonCount(1, 'data.items');
    });

    it('accepts an empty guest cart', function () {
        signedIn();

        $this->postJson('/api/v1/cart/merge', mergePayload([]))->assertOk()->assertJsonPath('adjustments', [])->assertJsonCount(0, 'data.items');
    });

    it('applies a repeated merge id only once and answers with the same result', function () {
        $user = signedIn();
        $existing = stockedOffer(stock: 20);
        $scarce = stockedOffer(stock: 3);
        $gone = stockedOffer(attributes: ['is_active' => false]);
        CartItem::factory()->for($user)->for($existing)->create(['quantity' => 2]);
        $payload = mergePayload([
            ['offer_id' => $existing->id, 'quantity' => 3],
            ['offer_id' => $scarce->id, 'quantity' => 5],
            ['offer_id' => $gone->id, 'quantity' => 1],
        ]);

        $first = $this->postJson('/api/v1/cart/merge', $payload)->assertOk();
        $retry = $this->postJson('/api/v1/cart/merge', $payload)->assertOk();

        expect($retry->json())->toBe($first->json())
            ->and($user->cartItems()->orderBy('id')->pluck('quantity')->all())->toBe([5, 3])
            ->and(CartMerge::query()->count())->toBe(1);
    });

    it('treats a merge id case-insensitively', function () {
        $user = signedIn();
        $offer = stockedOffer();
        $mergeId = (string) Str::uuid();

        $this->postJson('/api/v1/cart/merge', mergePayload([['offer_id' => $offer->id, 'quantity' => 2]], $mergeId))->assertOk();
        $this->postJson('/api/v1/cart/merge', mergePayload([['offer_id' => $offer->id, 'quantity' => 2]], strtoupper($mergeId)))->assertOk();

        expect($user->cartItems()->sole()->quantity)->toBe(2);
    });

    it('applies a new merge id again', function () {
        $user = signedIn();
        $offer = stockedOffer();

        $this->postJson('/api/v1/cart/merge', mergePayload([['offer_id' => $offer->id, 'quantity' => 2]]))->assertOk();
        $this->postJson('/api/v1/cart/merge', mergePayload([['offer_id' => $offer->id, 'quantity' => 2]]))->assertOk();

        expect($user->cartItems()->sole()->quantity)->toBe(4);
    });

    it('scopes merge ids per user', function () {
        $offer = stockedOffer();
        $payload = mergePayload([['offer_id' => $offer->id, 'quantity' => 1]]);
        $alice = signedIn();
        $this->postJson('/api/v1/cart/merge', $payload)->assertOk();
        $bob = signedIn();
        $this->postJson('/api/v1/cart/merge', $payload)->assertOk()->assertJsonCount(1, 'data.items');

        expect($alice->cartItems()->count())->toBe(1)->and($bob->cartItems()->count())->toBe(1);
    });

    it('forgets merge records after a week', function () {
        $user = signedIn();
        $offer = stockedOffer();
        $old = CartMerge::query()->create(['user_id' => $user->id, 'merge_id' => (string) Str::uuid(), 'adjustments' => []]);
        $old->forceFill(['created_at' => now()->subDays(8)])->save();

        $this->postJson('/api/v1/cart/merge', mergePayload([['offer_id' => $offer->id, 'quantity' => 1]]))->assertOk();

        $this->assertModelMissing($old);
        expect(CartMerge::query()->count())->toBe(1);
    });

    it('validates the payload', function (array $payload, string $field) {
        signedIn();

        $this->postJson('/api/v1/cart/merge', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no merge id' => [['items' => []], 'merge_id'],
        'merge id not a uuid' => [['merge_id' => 'abc', 'items' => []], 'merge_id'],
        'no items key' => [['merge_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'], 'items'],
        'too many items' => [['merge_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', 'items' => array_fill(0, 51, ['offer_id' => 1, 'quantity' => 1])], 'items'],
        'zero quantity' => [['merge_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', 'items' => [['offer_id' => 1, 'quantity' => 0]]], 'items.0.quantity'],
        'negative quantity' => [['merge_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', 'items' => [['offer_id' => 1, 'quantity' => -1]]], 'items.0.quantity'],
        'missing offer id' => [['merge_id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', 'items' => [['quantity' => 1]]], 'items.0.offer_id'],
    ]);
});
