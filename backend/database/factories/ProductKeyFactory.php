<?php

namespace Database\Factories;

use App\Enums\ProductKeyStatus;
use App\Models\Offer;
use App\Models\OrderItem;
use App\Models\ProductKey;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductKey>
 */
class ProductKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'code' => implode('-', str_split(Str::upper(Str::random(15)), 5)),
            'status' => ProductKeyStatus::Available,
            'reserved_until' => null,
            'order_item_id' => null,
        ];
    }

    /**
     * Held for a pending order; pass a past time for an expired hold.
     */
    public function reserved(?DateTimeInterface $until = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductKeyStatus::Reserved,
            'reserved_until' => $until ?? now()->addMinutes(15),
            'order_item_id' => OrderItem::factory()->state(['offer_id' => $attributes['offer_id']]),
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductKeyStatus::Sold,
            'reserved_until' => null,
            'order_item_id' => OrderItem::factory()->state(['offer_id' => $attributes['offer_id']]),
        ]);
    }
}
