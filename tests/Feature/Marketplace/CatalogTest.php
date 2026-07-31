<?php

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVersion;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

test('the published scope only returns published products', function () {
    Product::factory()->create();
    Product::factory()->draft()->create();
    Product::factory()->archived()->create();

    expect(Product::published()->count())->toBe(1);
});

test('price formatting distinguishes free from paid', function () {
    expect(Product::factory()->free()->make()->formattedPrice())->toBe('Free')
        ->and(Product::factory()->make(['price' => 2900])->formattedPrice())->toBe('$29.00')
        ->and(Product::factory()->make(['price' => 150])->formattedPrice())->toBe('$1.50');
});

test('a product is only purchasable when published and carrying a release', function () {
    $noVersion = Product::factory()->create();
    expect($noVersion->isPurchasable())->toBeFalse();

    ProductVersion::factory()->for($noVersion)->create();
    expect($noVersion->fresh()->isPurchasable())->toBeTrue();

    $draft = Product::factory()->draft()->withVersion()->create();
    expect($draft->isPurchasable())->toBeFalse();
});

test('the latest version is the most recently released one', function () {
    $product = Product::factory()->create();

    ProductVersion::factory()->for($product)->create([
        'version' => '1.0.0',
        'released_at' => now()->subMonth(),
    ]);
    $newest = ProductVersion::factory()->for($product)->create([
        'version' => '2.0.0',
        'released_at' => now(),
    ]);

    expect($product->latestVersion()->is($newest))->toBeTrue()
        ->and($product->latestVersion()->version)->toBe('2.0.0');
});

test('a product cannot carry the same version twice', function () {
    $product = Product::factory()->create();
    ProductVersion::factory()->for($product)->create(['version' => '1.0.0']);

    ProductVersion::factory()->for($product)->create(['version' => '1.0.0']);
})->throws(QueryException::class);

test('slugs are generated uniquely from a name', function () {
    Product::factory()->create(['slug' => 'my-theme']);

    expect(Product::generateSlug('My Theme'))->not->toBe('my-theme')
        ->and(Product::generateSlug('Totally New Name'))->toBe('totally-new-name')
        ->and(ProductCategory::generateSlug('E Commerce'))->toBe('e-commerce');
});

test('products resolve by slug in routes', function () {
    $product = Product::factory()->create(['slug' => 'route-key-test']);

    expect($product->getRouteKeyName())->toBe('slug')
        ->and($product->getRouteKey())->toBe('route-key-test');
});

test('categories expose their products and enums cast correctly', function () {
    $category = ProductCategory::factory()->create();
    Product::factory()->count(2)->create([
        'category_id' => $category->id,
        'type' => ProductType::Theme,
    ]);

    $product = $category->products()->first();

    expect($category->products()->count())->toBe(2)
        ->and($product->type)->toBe(ProductType::Theme)
        ->and($product->status)->toBe(ProductStatus::Published)
        ->and($product->category->is($category))->toBeTrue();
});

test('deleting a product removes its versions and images', function () {
    $product = Product::factory()->withVersion()->create();
    $product->images()->create(['path' => 'product-images/x.jpg', 'position' => 0]);

    $product->delete();

    expect(ProductVersion::where('product_id', $product->id)->exists())->toBeFalse()
        ->and(DB::table('product_images')->where('product_id', $product->id)->exists())->toBeFalse();
});

test('images are ordered by position', function () {
    $product = Product::factory()->create();
    $product->images()->create(['path' => 'b.jpg', 'position' => 2]);
    $product->images()->create(['path' => 'a.jpg', 'position' => 1]);

    expect($product->images()->pluck('path')->all())->toBe(['a.jpg', 'b.jpg']);
});

test('file sizes render in human units', function () {
    expect(ProductVersion::factory()->make(['file_size' => 0])->formattedFileSize())->toBe('—')
        ->and(ProductVersion::factory()->make(['file_size' => 512])->formattedFileSize())->toBe('512 B')
        ->and(ProductVersion::factory()->make(['file_size' => 2048])->formattedFileSize())->toBe('2 KB')
        ->and(ProductVersion::factory()->make(['file_size' => 5_242_880])->formattedFileSize())->toBe('5 MB');
});

test('product changes are written to the activity log', function () {
    // Pin both prices: logOnlyDirty means an unchanged value logs nothing.
    $product = Product::factory()->create(['price' => 1000]);

    $product->update(['price' => 9900]);

    expect(Activity::forSubject($product)
        ->where('log_name', 'product')
        ->where('description', 'updated')
        ->exists())->toBeTrue();
});
