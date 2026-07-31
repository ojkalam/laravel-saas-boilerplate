<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Livewire\Livewire;

test('guests can browse the storefront', function () {
    Product::factory()->count(3)->withVersion()->create();

    $this->get(route('marketplace.index'))->assertOk();
});

test('only published products are listed', function () {
    $published = Product::factory()->create(['name' => 'Visible Theme']);
    $draft = Product::factory()->draft()->create(['name' => 'Hidden Draft']);
    $archived = Product::factory()->archived()->create(['name' => 'Old Archived']);

    Livewire::test('pages::marketplace.index')
        ->assertSee('Visible Theme')
        ->assertDontSee('Hidden Draft')
        ->assertDontSee('Old Archived');
});

test('search matches name, summary, and description', function () {
    Product::factory()->create(['name' => 'Aurora Dashboard', 'summary' => 'x', 'description' => 'y']);
    Product::factory()->create(['name' => 'Zenith Store', 'summary' => 'aurora inside', 'description' => 'y']);
    Product::factory()->create(['name' => 'Nothing Here', 'summary' => 'x', 'description' => 'y']);

    Livewire::test('pages::marketplace.index')
        ->set('search', 'aurora')
        ->assertSee('Aurora Dashboard')
        ->assertSee('Zenith Store')
        ->assertDontSee('Nothing Here');
});

test('search is case insensitive and ignores wildcards', function () {
    Product::factory()->create(['name' => 'Aurora Dashboard']);
    Product::factory()->create(['name' => 'Other Product']);

    Livewire::test('pages::marketplace.index')
        ->set('search', 'AURORA')
        ->assertSee('Aurora Dashboard')
        ->assertDontSee('Other Product');

    // A bare % must not turn into "match everything".
    Livewire::test('pages::marketplace.index')
        ->set('search', '%')
        ->assertDontSee('Aurora Dashboard');
});

test('products can be filtered by type', function () {
    Product::factory()->theme()->create(['name' => 'Theme One']);
    Product::factory()->app()->create(['name' => 'App One']);

    Livewire::test('pages::marketplace.index')
        ->set('type', 'theme')
        ->assertSee('Theme One')
        ->assertDontSee('App One');
});

test('products can be filtered by category', function () {
    $ecommerce = ProductCategory::factory()->create(['name' => 'Ecommerce', 'slug' => 'ecommerce']);
    $blog = ProductCategory::factory()->create(['name' => 'Blog', 'slug' => 'blog']);

    Product::factory()->create(['name' => 'Shop Theme', 'category_id' => $ecommerce->id]);
    Product::factory()->create(['name' => 'Writer Theme', 'category_id' => $blog->id]);

    Livewire::test('pages::marketplace.index')
        ->set('category', 'ecommerce')
        ->assertSee('Shop Theme')
        ->assertDontSee('Writer Theme');
});

test('sorting by price orders results both ways', function () {
    Product::factory()->create(['name' => 'Cheap One', 'price' => 500]);
    Product::factory()->create(['name' => 'Costly One', 'price' => 9900]);

    Livewire::test('pages::marketplace.index')
        ->set('sort', 'price_asc')
        ->assertSeeInOrder(['Cheap One', 'Costly One']);

    Livewire::test('pages::marketplace.index')
        ->set('sort', 'price_desc')
        ->assertSeeInOrder(['Costly One', 'Cheap One']);
});

test('featured products lead the default sort', function () {
    Product::factory()->create(['name' => 'Regular Product']);
    Product::factory()->featured()->create(['name' => 'Featured Product']);

    Livewire::test('pages::marketplace.index')
        ->assertSeeInOrder(['Featured Product', 'Regular Product']);
});

test('changing a filter resets pagination', function () {
    Product::factory()->count(20)->create();

    Livewire::test('pages::marketplace.index')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('search', 'nothing-matches-this')
        ->assertSet('paginators.page', 1);
});

test('an empty result set shows the empty state and filters can be cleared', function () {
    Product::factory()->create(['name' => 'Real Product']);

    Livewire::test('pages::marketplace.index')
        ->set('search', 'zzzz-no-match')
        ->set('type', 'app')
        ->assertSee('Nothing matches those filters')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('type', '')
        ->assertSee('Real Product');
});

test('a published product detail page renders its content', function () {
    $product = Product::factory()->withVersion('2.1.0')->create([
        'name' => 'Aurora Theme',
        'summary' => 'A bright starting point',
        'description' => 'Long form description here',
        'price' => 4900,
    ]);

    $this->get(route('marketplace.show', $product))
        ->assertOk()
        ->assertSee('Aurora Theme')
        ->assertSee('A bright starting point')
        ->assertSee('Long form description here')
        ->assertSee('$49.00')
        ->assertSee('v2.1.0');
});

test('draft and archived products 404 on the storefront', function () {
    $this->get(route('marketplace.show', Product::factory()->draft()->create()))->assertNotFound();
    $this->get(route('marketplace.show', Product::factory()->archived()->create()))->assertNotFound();
});

test('guests see a sign-in call to action instead of a buy button', function () {
    $product = Product::factory()->withVersion()->create();

    $this->get(route('marketplace.show', $product))
        ->assertSee('Sign in to buy')
        ->assertDontSee('data-test="buy-button"', false);
});

test('a free product invites a download rather than a purchase', function () {
    $product = Product::factory()->free()->withVersion()->create();

    $this->get(route('marketplace.show', $product))->assertSee('Sign in to download');
});

test('a product without a release cannot be bought', function () {
    $product = Product::factory()->create();

    $this->get(route('marketplace.show', $product))->assertSee('Not available yet');
});

test('related products come from the same category and exclude the current one', function () {
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create(['name' => 'Current Product', 'category_id' => $category->id]);
    Product::factory()->create(['name' => 'Sibling Product', 'category_id' => $category->id]);
    Product::factory()->create(['name' => 'Unrelated Product']);

    $response = $this->get(route('marketplace.show', $product));

    $response->assertSee('Sibling Product')->assertDontSee('Unrelated Product');
});

test('the image gallery only selects images belonging to the product', function () {
    $product = Product::factory()->create();
    $first = $product->images()->create(['path' => 'a.jpg', 'position' => 1]);
    $second = $product->images()->create(['path' => 'b.jpg', 'position' => 2]);
    $foreign = Product::factory()->create()->images()->create(['path' => 'c.jpg', 'position' => 1]);

    Livewire::test('pages::marketplace.show', ['product' => $product])
        ->assertSet('activeImageId', $first->id)
        ->call('selectImage', $second->id)
        ->assertSet('activeImageId', $second->id)
        ->call('selectImage', $foreign->id)
        ->assertSet('activeImageId', $second->id);
});

test('an authenticated buyer is past the sign-in gate', function () {
    $product = Product::factory()->withVersion()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('marketplace.show', $product))
        ->assertOk()
        ->assertDontSee('Sign in to buy');
});
