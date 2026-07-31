<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseActivation>
 */
class LicenseActivationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'instance' => fake()->unique()->domainName(),
            'activated_at' => now(),
        ];
    }
}
