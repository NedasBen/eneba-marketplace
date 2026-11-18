<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the order history. Never includes keys: those are only on
 * the order detail (OrderResource) of a paid order.
 *
 * @mixin Order
 */
class OrderSummaryResource extends JsonResource
{
    public const PREVIEW_ITEMS = 3;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items->sortBy('id')->values();

        return [
            'reference' => $this->reference,
            'status' => $this->effectiveStatus()->value,
            'total' => Money::fromCents($this->total_cents),
            'item_count' => (int) $items->sum('quantity'),
            'created_at' => $this->created_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'product_names' => $items->take(self::PREVIEW_ITEMS)->map(fn (OrderItem $item): string => $item->product_name)->all(),
            'more_products' => max(0, $items->count() - self::PREVIEW_ITEMS),
            'cover_image_url' => $items->first()?->product->game->cover_image_url,
        ];
    }
}
