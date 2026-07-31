<?php

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Livewire\Livewire;

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

/**
 * @return array{0: User, 1: Team}
 */
function portalUser(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $user->forceFill(['current_team_id' => $team->id])->save();

    return [$user, $team];
}

test('a team with no purchases sees the empty state', function () {
    [$user] = portalUser();

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertOk()
        ->assertSee('Nothing purchased yet');
});

test('the portal shows license keys and downloadable versions', function () {
    [$user, $team] = portalUser();

    $product = Product::factory()->create(['name' => 'Aurora Theme']);
    ProductVersion::factory()->for($product)->create(['version' => '1.4.0']);
    $license = License::factory()->create([
        'team_id' => $team->id,
        'product_id' => $product->id,
        'key' => 'AAAA-BBBB-CCCC-DDDD',
    ]);

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertOk()
        ->assertSee('Aurora Theme')
        ->assertSee('AAAA-BBBB-CCCC-DDDD')
        ->assertSee('v1.4.0')
        ->assertSee('Download');
});

test('a version outside the updates window is shown as locked', function () {
    [$user, $team] = portalUser();

    $product = Product::factory()->create();
    ProductVersion::factory()->for($product)->create([
        'version' => '9.0.0',
        'released_at' => now(),
    ]);
    License::factory()->updatesExpired()->create([
        'team_id' => $team->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertSee('Renew for access')
        ->assertSee('Updates expired');
});

test('a revoked license offers no downloads', function () {
    [$user, $team] = portalUser();

    $product = Product::factory()->create();
    ProductVersion::factory()->for($product)->create();
    License::factory()->revoked()->create([
        'team_id' => $team->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertSee('Revoked')
        ->assertDontSee('data-test="download-', false);
});

test('one team never sees another team\'s licenses', function () {
    [$user, $team] = portalUser();
    [, $otherTeam] = portalUser();

    License::factory()->create([
        'team_id' => $team->id,
        'product_id' => Product::factory()->create(['name' => 'Mine Theme'])->id,
        'key' => 'MINE-MINE-MINE-MINE',
    ]);
    License::factory()->create([
        'team_id' => $otherTeam->id,
        'product_id' => Product::factory()->create(['name' => 'Theirs Theme'])->id,
        'key' => 'THRS-THRS-THRS-THRS',
    ]);

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertSee('MINE-MINE-MINE-MINE')
        ->assertDontSee('THRS-THRS-THRS-THRS')
        ->assertDontSee('Theirs Theme');
});

test('a buyer can release one of their activations', function () {
    [$user, $team] = portalUser();

    $license = License::factory()->seats(2)->create(['team_id' => $team->id]);
    $activation = LicenseActivation::factory()->for($license)->create(['instance' => 'example.com']);

    $this->actingAs($user);
    app(CurrentTeam::class)->set($team);

    Livewire::test('pages::purchases.index')
        ->assertSee('example.com')
        ->call('deactivate', $activation->id);

    expect(LicenseActivation::whereKey($activation->id)->exists())->toBeFalse()
        ->and($license->fresh()->remainingActivations())->toBe(2);
});

test('a buyer cannot release an activation belonging to another team', function () {
    [$user, $team] = portalUser();
    [, $otherTeam] = portalUser();

    $foreignLicense = License::factory()->create(['team_id' => $otherTeam->id]);
    $foreignActivation = LicenseActivation::factory()->for($foreignLicense)->create();

    $this->actingAs($user);
    app(CurrentTeam::class)->set($team);

    Livewire::test('pages::purchases.index')
        ->call('deactivate', $foreignActivation->id)
        ->assertNotFound();

    expect(LicenseActivation::whereKey($foreignActivation->id)->exists())->toBeTrue();
});

test('the order history lists the team\'s orders with status and total', function () {
    [$user, $team] = portalUser();

    $license = License::factory()->create(['team_id' => $team->id]);
    $order = Order::factory()->paid()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'total' => 4900,
    ]);
    $order->items()->create([
        'product_id' => $license->product_id,
        'product_name' => 'Aurora Theme',
        'unit_price' => 4900,
    ]);

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertSee($order->number)
        ->assertSee('Aurora Theme')
        ->assertSee('$49.00')
        ->assertSee('Paid');
});

test('guests are sent to login', function () {
    $this->get(route('purchases.index'))->assertRedirect(route('login', absolute: false));
});
