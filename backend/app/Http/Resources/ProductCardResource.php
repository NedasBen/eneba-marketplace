<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product as shown on store and homepage cards.
 *
 * Expects `game`, `platform` and `region` to be eager loaded.
 *
 * @mixin Product
 */
class ProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'game' => [
                'name' => $this->game->name,
                'slug' => $this->game->slug,
                'cover_image_url' => $this->game->cover_image_url,
            ],
            'platform' => [
                'slug' => $this->platform->slug,
                'name' => $this->platform->name,
                'activation_service' => $this->platform->activation_service,
            ],
            'region' => [
                'slug' => $this->region->slug,
                'name' => $this->region->name,
            ],
            'price_from' => Money::fromNullableCents($this->min_price_cents),
            'retail_price' => Money::fromCents($this->retail_price_cents),
            'discount_percent' => $this->discountPercent(),
            'offers_count' => $this->offers_count,
            'in_stock' => $this->min_price_cents !== null,
        ];
    }
}
