<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'total_cents' => fake()->numberBetween(199, 15000),
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(15),
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
