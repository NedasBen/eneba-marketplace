<?php

namespace App\Services\Checkout;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Checkout couldn't reserve every key in the cart. Nothing was reserved:
 * the transaction rolled back. Rendered as 409 with the short lines.
 */
final class InsufficientStockException extends RuntimeException
{
    /**
     * @param  list<array{offer_id: int, product_name: string, seller_name: string, requested: int, available: int}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct('Some items in your cart are no longer available in the quantity you chose.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'shortages' => $this->shortages], 409);
    }
}
