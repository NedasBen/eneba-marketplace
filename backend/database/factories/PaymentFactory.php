<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'simulated',
            'provider_reference' => 'sim_'.Str::lower(Str::random(16)),
            'status' => PaymentStatus::Succeeded,
            'failure_code' => null,
            'amount_cents' => fake()->numberBetween(199, 15000),
            'card_last4' => '4242',
        ];
    }

    public function declined(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Declined,
            'failure_code' => 'card_declined',
            'card_last4' => '0002',
        ]);
    }
}
