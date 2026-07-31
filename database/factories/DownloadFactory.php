<?php

namespace Database\Factories;

use App\Models\Download;
use App\Models\License;
use App\Models\ProductVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Download>
 */
class DownloadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'license_id' => License::factory(),
            'product_version_id' => ProductVersion::factory(),
            'user_id' => User::factory(),
            'ip' => fake()->ipv4(),
        ];
    }
}
