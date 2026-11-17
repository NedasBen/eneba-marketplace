<?php

use App\Services\Catalog\ProductStockAggregator;

it('derives the cheapest in-stock price, in-stock offer count and sold keys from offers and keys', function () {
    $productId = insertCatalogProduct();

    insertKeys(insertOffer($productId, 1000), 'available', 2);
    insertKeys(insertOffer($productId, 600), 'sold', 3);                                  // sold out: not in stock
    insertKeys(insertOffer($productId, 500, isActive: false), 'available');               // inactive
    insertKeys(insertOffer($productId, 700), 'reserved', reservedUntil: now()->addMinutes(10)); // still held
    insertKeys(insertOffer($productId, 800), 'reserved', reservedUntil: now()->subMinute());    // hold expired

    $changed = app(ProductStockAggregator::class)->refresh([$productId]);

    expect($changed)->toBe(1)
        ->and(productStats($productId))->toEqual((object) [
            'min_price_cents' => 800,
            'offers_count' => 2,
            'sold_count' => 3,
        ]);
});

it('clears the price and counts of a product without stock', function () {
    $productId = insertCatalogProduct(['min_price_cents' => 1999, 'offers_count' => 4, 'sold_count' => 7]);

    app(ProductStockAggregator::class)->refresh([$productId]);

    expect(productStats($productId))->toEqual((object) ['min_price_cents' => null, 'offers_count' => 0, 'sold_count' => 0]);
});

it('only touches the requested products', function () {
    $requestedProductId = insertCatalogProduct(['offers_count' => 9]);
    $otherProductId = insertCatalogProduct(['offers_count' => 9]);

    app(ProductStockAggregator::class)->refresh([$requestedProductId]);

    expect(productStats($requestedProductId)->offers_count)->toBe(0)
        ->and(productStats($otherProductId)->offers_count)->toBe(9);
});

it('does not rewrite products whose stats are already correct', function () {
    $productId = insertCatalogProduct();
    insertKeys(insertOffer($productId, 1000), 'available');
    $aggregator = app(ProductStockAggregator::class);

    expect($aggregator->refresh([$productId]))->toBe(1)
        ->and($aggregator->refresh([$productId]))->toBe(0);
});
