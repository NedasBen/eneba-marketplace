<?php

use App\Models\Offer;
use App\Models\ProductKey;

it('returns the current price, stock and status of the requested offers', function () {
    $product = catalogProduct(attributes: ['slug' => 'elden-ring-steam-key-global', 'name' => 'ELDEN RING Steam Key GLOBAL']);
    $inStock = Offer::factory()->for($product)->create(['price_cents' => 2999]);
    ProductKey::factory()->count(4)->for($inStock)->create();
    $paused = Offer::factory()->for($product)->inactive()->create(['price_cents' => 1999]);

    $this->getJson("/api/v1/offers?ids={$paused->id},{$inStock->id}")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$inStock->id, $paused->id])
        ->assertJsonPath('data.0', [
            'id' => $inStock->id,
            'is_active' => true,
            'price' => ['amount_cents' => 2999, 'currency' => 'EUR'],
            'stock' => 4,
            'seller' => ['name' => $inStock->seller->name],
            'product' => ['slug' => 'elden-ring-steam-key-global', 'name' => 'ELDEN RING Steam Key GLOBAL'],
        ])
        ->assertJsonPath('data.1.is_active', false)
        ->assertJsonPath('data.1.stock', 0);
});

it('leaves unknown offer ids out of the response', function () {
    $offer = Offer::factory()->create();

    $this->getJson("/api/v1/offers?ids={$offer->id},999999")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$offer->id]);
});

it('rejects missing, malformed or too many ids', function (string $query) {
    $this->getJson("/api/v1/offers{$query}")->assertUnprocessable();
})->with([
    'no ids' => [''],
    'not numbers' => ['?ids=abc'],
    'too many' => ['?ids='.implode(',', range(1, 51))],
]);
