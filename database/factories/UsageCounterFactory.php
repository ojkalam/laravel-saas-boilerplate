<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\UsageCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageCounter>
 */
class UsageCounterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'metric' => fake()->randomElement(['api_calls', 'exports']),
            'period_start' => now()->startOfMonth(),
            'value' => fake()->numberBetween(0, 100),
        ];
    }
}
