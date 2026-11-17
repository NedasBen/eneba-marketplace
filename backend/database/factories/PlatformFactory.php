<?php

namespace Database\Factories;

use App\Enums\GamePlatform;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Platform>
 */
class PlatformFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name,
            'activation_service' => 'Steam',
        ];
    }

    public function forPlatform(GamePlatform $platform): static
    {
        return $this->state(fn (): array => [
            'name' => $platform->label(),
            'slug' => $platform->value,
            'activation_service' => $platform->activationService(),
        ]);
    }
}
