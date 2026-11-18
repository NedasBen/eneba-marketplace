<?php

namespace App\Http\Resources;

use App\Models\Offer;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One seller's offer on the product page. Expects `seller` eager loaded and
 * `stock` from Offer::withStock().
 *
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price' => Money::fromCents($this->price_cents),
            'stock' => (int) $this->stock,
            'seller' => [
                'name' => $this->seller->name,
                'slug' => $this->seller->slug,
                // Positive feedback, 0-100.
                'rating' => (float) $this->seller->rating,
                'feedback_count' => $this->seller->feedback_count,
                'country_code' => $this->seller->country_code,
            ],
        ];
    }
}
