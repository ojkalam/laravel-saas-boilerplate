<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->bothify('??####'),
            'status' => OrderStatus::Pending,
            'currency' => 'usd',
            'total' => 2900,
            'stripe_checkout_session_id' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Refunded,
            'paid_at' => now()->subDay(),
            'refunded_at' => now(),
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'total' => 0,
        ]);
    }
}
