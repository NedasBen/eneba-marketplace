<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Services\Cart\CartService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One cart line with its offer's CURRENT price and stock. `price_seen` is
 * the price when it was added, for the "price changed" notice only.
 *
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->offer;
        $product = $offer->product;

        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'quantity' => $this->quantity,
            'is_active' => $offer->is_active,
            'stock' => (int) $offer->stock,
            'max_quantity' => app(CartService::class)->limitFor($offer),
            'unit_price' => Money::fromCents($offer->price_cents),
            'price_seen' => Money::fromCents($this->price_seen_cents),
            'line_total' => Money::fromCents($offer->price_cents * $this->quantity),
            'seller' => ['name' => $offer->seller->name],
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'cover_image_url' => $product->game->cover_image_url,
                'platform_name' => $product->platform->name,
                'region_name' => $product->region->name,
            ],
        ];
    }
}
