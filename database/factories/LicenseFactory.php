<?php

namespace Database\Factories;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'product_id' => Product::factory(),
            'order_item_id' => null,
            'key' => collect(range(1, 4))->map(fn () => Str::upper(Str::random(4)))->implode('-'),
            'status' => LicenseStatus::Active,
            'activation_limit' => 1,
            'expires_at' => now()->addYear(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LicenseStatus::Revoked,
        ]);
    }

    /**
     * The updates window has closed — the key still works, but new
     * releases are off limits.
     */
    public function updatesExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function seats(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'activation_limit' => $limit,
        ]);
    }
}
