<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVersion>
 */
class ProductVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'version' => fake()->numberBetween(1, 3).'.'.fake()->numberBetween(0, 9).'.'.fake()->numberBetween(0, 9),
            'changelog' => fake()->sentences(3, asText: true),
            'file_path' => 'releases/'.Str::lower(Str::random(12)).'.zip',
            'file_size' => fake()->numberBetween(50_000, 5_000_000),
            'released_at' => now(),
        ];
    }
}
