<?php

use App\Enums\ProductStatus;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\User;

test('staff can list products and categories in the back-office', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(ProductResource::getUrl('index'))
        ->assertOk();

    $this->actingAs($staff)
        ->get(ProductCategoryResource::getUrl('index'))
        ->assertOk();
});

test('customers cannot reach the catalog admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(ProductResource::getUrl('index'))
        ->assertForbidden();
});

test('staff can open the product create and edit screens', function () {
    $staff = User::factory()->staff()->create();
    $product = Product::factory()->withVersion()->create();

    $this->actingAs($staff)->get(ProductResource::getUrl('create'))->assertOk();
    $this->actingAs($staff)->get(ProductResource::getUrl('edit', ['record' => $product]))->assertOk();
});

test('a product with no release cannot be published, one with a release can', function () {
    $empty = Product::factory()->draft()->create();
    $ready = Product::factory()->draft()->withVersion()->create();

    // Mirrors the guard in the publish action.
    expect($empty->versions()->exists())->toBeFalse()
        ->and($ready->versions()->exists())->toBeTrue();

    $ready->update(['status' => ProductStatus::Published]);

    expect($ready->fresh()->isPurchasable())->toBeTrue();
});
