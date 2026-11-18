<?php

namespace App\Http\Resources;

use App\Models\Offer;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Current state of an offer, for the guest cart to compare against the price
 * it saved when the item was added. Display only: checkout always recomputes
 * prices from the offers on the server.
 *
 * @mixin Offer
 */
class OfferLookupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_active' => $this->is_active,
            'price' => Money::fromCents($this->price_cents),
            'stock' => (int) $this->stock,
            'seller' => ['name' => $this->seller->name],
            'product' => [
                'slug' => $this->product->slug,
                'name' => $this->product->name,
            ],
        ];
    }
}
