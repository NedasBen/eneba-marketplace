<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The whole cart. `total` counts only lines that can be bought as they
 * stand (active, enough stock), at current prices; checkout recomputes it.
 *
 * @property Collection<int, CartItem> $resource
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $purchasable = $this->resource->filter(
            fn (CartItem $line): bool => $line->offer->is_active && (int) $line->offer->stock >= $line->quantity,
        );

        return [
            'items' => CartItemResource::collection($this->resource),
            'item_count' => (int) $this->resource->sum('quantity'),
            'total' => Money::fromCents((int) $purchasable->sum(fn (CartItem $line): int => $line->offer->price_cents * $line->quantity)),
        ];
    }
}
