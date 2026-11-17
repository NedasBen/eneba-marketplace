<?php

namespace Database\Factories;

use App\Enums\HomepageBlockType;
use App\Models\HomepageBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageBlock>
 */
class HomepageBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => HomepageBlockType::BestSellers,
            'title' => fake()->words(2, true),
            'position' => fake()->numberBetween(1, 20),
            'is_active' => true,
            'settings' => ['limit' => 12],
        ];
    }
}
