<?php

namespace Database\Factories;

use App\Models\CartItem;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'offer_id' => Offer::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'price_seen_cents' => fn (array $attributes): int => Offer::query()->whereKey($attributes['offer_id'])->value('price_cents') ?? 999,
        ];
    }
}
