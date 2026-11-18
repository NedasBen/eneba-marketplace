<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\ProductKeyStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductKey;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as its owner sees it. Prices are the checkout snapshots. Keys
 * are included only once the order is paid. `server_time` lets the client
 * run the reservation countdown against the server's clock.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * Relations the resource reads; load them with `$order->load(OrderResource::RELATIONS)`.
     */
    public const RELATIONS = [
        'items.product.game:id,cover_image_url',
        'items.product.platform:id,name',
        'items.product.region:id,name',
        'items.keys',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->effectiveStatus();
        $paid = $status === OrderStatus::Paid;

        return [
            'reference' => $this->reference,
            'status' => $status->value,
            'total' => Money::fromCents($this->total_cents),
            'expires_at' => $this->expires_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'items' => $this->items->map(fn (OrderItem $item): array => $this->item($item, $paid))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(OrderItem $item, bool $paid): array
    {
        $line = [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'seller_name' => $item->seller_name,
            'product_slug' => $item->product->slug,
            'cover_image_url' => $item->product->game->cover_image_url,
            'platform_name' => $item->product->platform->name,
            'region_name' => $item->product->region->name,
            'quantity' => $item->quantity,
            'unit_price' => Money::fromCents($item->unit_price_cents),
            'line_total' => Money::fromCents($item->unit_price_cents * $item->quantity),
        ];

        if (! $paid) {
            return $line;
        }

        return $line + [
            'keys' => $item->keys
                ->where('status', ProductKeyStatus::Sold)
                ->sortBy('id')
                ->map(fn (ProductKey $key): array => ['id' => $key->id, 'code' => $key->code])
                ->values()
                ->all(),
        ];
    }
}
