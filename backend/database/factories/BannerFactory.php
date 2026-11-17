<?php

namespace Database\Factories;

use App\Models\Banner;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'subtitle' => fake()->sentence(),
            'image_url' => fake()->imageUrl(1920, 620),
            'cta_label' => 'Shop now',
            'product_id' => Product::factory(),
            'position' => fake()->numberBetween(1, 10),
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
