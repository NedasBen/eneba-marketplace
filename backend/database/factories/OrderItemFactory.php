<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'offer_id' => Offer::factory(),
            'product_id' => fn (array $attributes): int => Offer::query()->findOrFail($attributes['offer_id'])->product_id,
            'product_name' => fake()->words(3, true),
            'seller_name' => fake()->company(),
            'unit_price_cents' => fake()->numberBetween(199, 6999),
            'quantity' => 1,
        ];
    }
}
