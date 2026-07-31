<?php

use App\Actions\Marketplace\CreateOrder;
use App\Enums\OrderStatus;
use App\Mail\OrderReceiptMail;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

/**
 * A signed-in buyer whose current team is set, as the middleware would.
 *
 * @return array{0: User, 1: Team}
 */
function buyer(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $user->forceFill(['current_team_id' => $team->id])->save();

    return [$user, $team];
}

test('an order records the product name and price at purchase time', function () {
    [$user, $team] = buyer();
    $product = Product::factory()->withVersion()->create(['name' => 'Aurora', 'price' => 4900]);

    $order = app(CreateOrder::class)->handle($team, $user, $product);

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total)->toBe(4900)
        ->and($order->team_id)->toBe($team->id)
        ->and($order->user_id)->toBe($user->id)
        ->and($order->number)->toStartWith('ORD-')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe('Aurora')
        ->and($order->items->first()->unit_price)->toBe(4900);
});

test('an order keeps its own copy of the name after the catalog changes', function () {
    [$user, $team] = buyer();
    $product = Product::factory()->withVersion()->create(['name' => 'Original Name', 'price' => 2900]);

    $order = app(CreateOrder::class)->handle($team, $user, $product);
    $product->update(['name' => 'Renamed Later', 'price' => 9900]);

    expect($order->items->first()->product_name)->toBe('Original Name')
        ->and($order->items->first()->unit_price)->toBe(2900);
});

test('a product without a release cannot be ordered', function () {
    [$user, $team] = buyer();
    $product = Product::factory()->create();

    app(CreateOrder::class)->handle($team, $user, $product);
})->throws(ValidationException::class);

test('a draft product cannot be ordered', function () {
    [$user, $team] = buyer();
    $product = Product::factory()->draft()->withVersion()->create();

    app(CreateOrder::class)->handle($team, $user, $product);
})->throws(ValidationException::class);

test('order numbers are unique', function () {
    $numbers = collect(range(1, 25))->map(fn () => Order::generateNumber());

    expect($numbers->unique())->toHaveCount(25);
});

test('a free product is fulfilled immediately without touching stripe', function () {
    Mail::fake();

    [$user, $team] = buyer();
    $product = Product::factory()->free()->withVersion()->create(['name' => 'Free Starter']);

    $this->actingAs($user)
        ->post(route('checkout.store', $product))
        ->assertRedirect(route('purchases.index', absolute: false));

    app(CurrentTeam::class)->set($team);

    $order = Order::query()->firstOrFail();

    expect($order->isPaid())->toBeTrue()
        ->and($order->total)->toBe(0)
        ->and($order->stripe_checkout_session_id)->toBeNull()
        ->and(License::query()->count())->toBe(1)
        ->and(License::query()->first()->product_id)->toBe($product->id);

    Mail::assertSent(OrderReceiptMail::class);
});

test('a free product grants a license with the configured limits', function () {
    Mail::fake();

    [$user, $team] = buyer();
    $product = Product::factory()->free()->withVersion()->create();

    $this->actingAs($user)->post(route('checkout.store', $product));

    app(CurrentTeam::class)->set($team);
    $license = License::query()->firstOrFail();

    expect($license->key)->toMatch('/^[A-Z0-9]{4}(-[A-Z0-9]{4}){3}$/')
        ->and($license->isActive())->toBeTrue()
        ->and($license->activation_limit)->toBe(config('marketplace.licenses.activation_limit'))
        ->and($license->expires_at->isFuture())->toBeTrue();
});

test('guests cannot start a checkout', function () {
    $product = Product::factory()->withVersion()->create();

    $this->post(route('checkout.store', $product))->assertRedirect(route('login', absolute: false));

    expect(Order::withoutGlobalScope('team')->count())->toBe(0);
});

test('a draft product cannot be checked out over http', function () {
    [$user] = buyer();
    $product = Product::factory()->draft()->withVersion()->create();

    $this->actingAs($user)->post(route('checkout.store', $product))->assertNotFound();

    expect(Order::withoutGlobalScope('team')->count())->toBe(0);
});

test('license keys are unique across many issuances', function () {
    $keys = collect(range(1, 50))->map(fn () => License::generateKey());

    expect($keys->unique())->toHaveCount(50);
});
