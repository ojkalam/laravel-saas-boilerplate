<?php

use App\Actions\Marketplace\RefundOrder;
use App\Enums\LicenseStatus;
use App\Filament\Resources\Licenses\LicenseResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Widgets\MarketplaceRevenueWidget;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Livewire\Livewire;

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

test('staff can list orders and licenses across every team', function () {
    $staff = User::factory()->staff()->create();

    Order::factory()->paid()->create(['team_id' => Team::factory()]);
    Order::factory()->paid()->create(['team_id' => Team::factory()]);
    License::factory()->create(['team_id' => Team::factory()]);

    $this->actingAs($staff)->get(OrderResource::getUrl('index'))->assertOk();
    $this->actingAs($staff)->get(LicenseResource::getUrl('index'))->assertOk();
});

test('customers cannot reach the order or license admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(OrderResource::getUrl('index'))->assertForbidden();
    $this->actingAs($user)->get(LicenseResource::getUrl('index'))->assertForbidden();
});

test('orders and licenses cannot be created by hand', function () {
    expect(OrderResource::canCreate())->toBeFalse()
        ->and(LicenseResource::canCreate())->toBeFalse();
});

test('refunding an order from the back-office revokes its licenses', function () {
    $team = Team::factory()->create();
    $order = Order::factory()->paid()->create(['team_id' => $team->id]);
    $item = $order->items()->create([
        'product_id' => Product::factory()->create()->id,
        'product_name' => 'Aurora Theme',
        'unit_price' => 4900,
    ]);
    $license = License::factory()->create([
        'team_id' => $team->id,
        'product_id' => $item->product_id,
        'order_item_id' => $item->id,
    ]);

    app(RefundOrder::class)->handle($order);

    expect($order->fresh()->isRefunded())->toBeTrue()
        ->and($license->fresh()->status)->toBe(LicenseStatus::Revoked);
});

test('the revenue widget totals only paid orders across teams', function () {
    Order::factory()->paid()->create(['team_id' => Team::factory(), 'total' => 4900]);
    Order::factory()->paid()->create(['team_id' => Team::factory(), 'total' => 2900]);
    Order::factory()->create(['team_id' => Team::factory(), 'total' => 9900]); // pending
    Order::factory()->refunded()->create(['team_id' => Team::factory(), 'total' => 1900]);

    $paidTotal = Order::acrossTeams()->where('status', 'paid')->sum('total');

    expect($paidTotal)->toBe(7800);
});

test('the revenue widget reports figures spanning every team', function () {
    $staff = User::factory()->staff()->create();

    Order::factory()->paid()->create(['team_id' => Team::factory(), 'total' => 4900]);
    Order::factory()->paid()->create(['team_id' => Team::factory(), 'total' => 2900]);
    License::factory()->create(['team_id' => Team::factory()]);

    $this->actingAs($staff);

    Livewire::test(MarketplaceRevenueWidget::class)
        ->assertOk()
        ->assertSee('Revenue this month')
        ->assertSee('$78.00')
        ->assertSee('Active licenses');
});
