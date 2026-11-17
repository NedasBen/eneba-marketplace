<?php

namespace Database\Factories;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'rating' => fake()->randomFloat(2, 85, 99.9),
            'feedback_count' => fake()->numberBetween(50, 90_000),
            'country_code' => fake()->countryCode(),
        ];
    }
}
