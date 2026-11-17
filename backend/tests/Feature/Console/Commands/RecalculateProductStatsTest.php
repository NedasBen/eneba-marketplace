<?php

use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductKey;

it('repairs drifted product stats and reports how many changed', function () {
    $product = Product::factory()->create(['min_price_cents' => 100, 'offers_count' => 5, 'sold_count' => 0]);
    ProductKey::factory()->for(Offer::factory()->for($product)->create(['price_cents' => 2499]))->create();
    ProductKey::factory()->count(2)->for(Offer::factory()->for($product)->create(['price_cents' => 1999]))->sold()->create();

    $this->artisan('catalog:recalculate-product-stats')
        ->expectsOutputToContain('1 product(s) updated')
        ->assertSuccessful();

    expect($product->fresh()->only(['min_price_cents', 'offers_count', 'sold_count']))
        ->toBe(['min_price_cents' => 2499, 'offers_count' => 1, 'sold_count' => 2]);
});

it('only reports drift with --dry-run', function () {
    $product = Product::factory()->create(['offers_count' => 3]);

    $this->artisan('catalog:recalculate-product-stats', ['--dry-run' => true])
        ->expectsOutputToContain('1 product(s) have drifted')
        ->assertSuccessful();

    expect($product->fresh()->offers_count)->toBe(3);
});
