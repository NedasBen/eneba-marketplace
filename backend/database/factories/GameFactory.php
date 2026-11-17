<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'steam_app_id' => fake()->unique()->numberBetween(10, 9_999_999),
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'cover_image_url' => fake()->imageUrl(600, 900),
            'header_image_url' => fake()->imageUrl(460, 215),
            'hero_image_url' => fake()->imageUrl(1920, 620),
            'screenshots' => [fake()->imageUrl(1920, 1080)],
            'released_at' => fake()->dateTimeBetween('-8 years', '-1 month'),
            'metacritic' => fake()->optional()->numberBetween(55, 97),
            'developer' => fake()->company(),
            'publisher' => fake()->company(),
        ];
    }
}
