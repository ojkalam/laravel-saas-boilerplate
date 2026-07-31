<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(rtrim(fake()->sentence(3), '.'));

        return [
            'category_id' => ProductCategory::factory(),
            'type' => fake()->randomElement(ProductType::cases()),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraphs(3, asText: true),
            'price' => fake()->randomElement([1900, 2900, 4900, 9900]),
            'status' => ProductStatus::Published,
            'featured' => false,
            'downloads_count' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Draft,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Archived,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    public function theme(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Theme,
        ]);
    }

    public function app(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::App,
        ]);
    }

    /**
     * A product with one release, i.e. actually purchasable.
     */
    public function withVersion(string $version = '1.0.0'): static
    {
        return $this->afterCreating(function (Product $product) use ($version): void {
            ProductVersionFactory::new()
                ->for($product)
                ->create(['version' => $version]);
        });
    }
}
