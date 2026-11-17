<?php

use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductKey;
use App\Services\Catalog\ProductStockAggregator;

/**
 * @return array{min_price_cents: int|null, offers_count: int, sold_count: int}
 */
function storedProductStats(Product $product): array
{
    return $product->fresh()->only(['min_price_cents', 'offers_count', 'sold_count']);
}

it('derives the cheapest in-stock price, in-stock offer count and sold keys from offers and keys', function () {
    $product = Product::factory()->create();
    $offer = fn (int $priceCents, bool $isActive = true): Offer => Offer::factory()->for($product)->create(['price_cents' => $priceCents, 'is_active' => $isActive]);

    ProductKey::factory()->count(2)->for($offer(1000))->create();
    ProductKey::factory()->count(3)->for($offer(600))->sold()->create();                 // sold out
    ProductKey::factory()->for($offer(500, isActive: false))->create();                  // listing paused
    ProductKey::factory()->for($offer(700))->reserved(now()->addMinutes(10))->create();  // still held
    ProductKey::factory()->for($offer(800))->reserved(now()->subMinute())->create();     // hold expired

    $changed = app(ProductStockAggregator::class)->refresh([$product->id]);

    expect($changed)->toBe(1)
        ->and(storedProductStats($product))->toBe(['min_price_cents' => 800, 'offers_count' => 2, 'sold_count' => 3]);
});

it('clears the price and counts of a product without stock', function () {
    $product = Product::factory()->create(['min_price_cents' => 1999, 'offers_count' => 4, 'sold_count' => 7]);

    app(ProductStockAggregator::class)->refresh([$product->id]);

    expect(storedProductStats($product))->toBe(['min_price_cents' => null, 'offers_count' => 0, 'sold_count' => 0]);
});

it('only touches the requested products', function () {
    $requestedProduct = Product::factory()->create(['offers_count' => 9]);
    $otherProduct = Product::factory()->create(['offers_count' => 9]);

    app(ProductStockAggregator::class)->refresh([$requestedProduct->id]);

    expect($requestedProduct->fresh()->offers_count)->toBe(0)
        ->and($otherProduct->fresh()->offers_count)->toBe(9);
});

it('does not rewrite products whose stats are already correct', function () {
    $product = Product::factory()->create();
    ProductKey::factory()->for(Offer::factory()->for($product))->create();
    $aggregator = app(ProductStockAggregator::class);

    expect($aggregator->refresh([$product->id]))->toBe(1)
        ->and($aggregator->refresh([$product->id]))->toBe(0);
});
