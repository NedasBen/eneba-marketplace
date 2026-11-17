<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true)).' Steam Key GLOBAL';

        return [
            'game_id' => Game::factory(),
            'platform_id' => Platform::factory(),
            'region_id' => Region::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'retail_price_cents' => fake()->randomElement([1999, 2999, 3999, 4999, 5999, 6999]),
        ];
    }
}
