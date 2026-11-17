<?php

it('repairs drifted product stats and reports how many changed', function () {
    $driftedProductId = insertCatalogProduct(['min_price_cents' => 100, 'offers_count' => 5, 'sold_count' => 0]);
    insertKeys(insertOffer($driftedProductId, 2499), 'available');
    insertKeys(insertOffer($driftedProductId, 1999), 'sold', 2);

    $this->artisan('catalog:recalculate-product-stats')
        ->expectsOutputToContain('1 product(s) updated')
        ->assertSuccessful();

    expect(productStats($driftedProductId))->toEqual((object) [
        'min_price_cents' => 2499,
        'offers_count' => 1,
        'sold_count' => 2,
    ]);
});

it('only reports drift with --dry-run', function () {
    $productId = insertCatalogProduct(['offers_count' => 3]);

    $this->artisan('catalog:recalculate-product-stats', ['--dry-run' => true])
        ->expectsOutputToContain('1 product(s) have drifted')
        ->assertSuccessful();

    expect(productStats($productId)->offers_count)->toBe(3);
});
