<?php

use App\Features\Api;
use App\Features\AuditLog;
use App\Features\Sso;
use App\Listeners\HandleStripeWebhook;
use App\Models\Team;
use App\Support\CurrentTeam;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Pennant\Feature;

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

test('a trialing team gets the trial plan features', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->addDays(7)]);

    expect(Feature::for($team)->active(AuditLog::class))->toBeTrue()
        ->and(Feature::for($team)->active(Api::class))->toBeTrue()
        ->and(Feature::for($team)->active(Sso::class))->toBeFalse();
});

test('a free team gets no premium features', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);

    expect(Feature::for($team)->active(AuditLog::class))->toBeFalse()
        ->and(Feature::for($team)->active(Api::class))->toBeFalse()
        ->and(Feature::for($team)->active(Sso::class))->toBeFalse();
});

test('an enterprise team gets every feature', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_features_test',
        'stripe_status' => 'active',
        'stripe_price' => config('plans.plans.enterprise.stripe_monthly'),
        'quantity' => 1,
    ]);

    expect(Feature::for($team)->active(Sso::class))->toBeTrue()
        ->and(Feature::for($team)->active(Api::class))->toBeTrue();
});

test('features resolve against the current team by default', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->addDays(7)]);
    app(CurrentTeam::class)->set($team);

    expect(Feature::active(AuditLog::class))->toBeTrue();
});

test('a subscription webhook purges cached feature values', function () {
    $team = Team::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'stripe_id' => 'cus_flag_purge',
    ]);

    // Resolve and store the free-plan value.
    expect(Feature::for($team)->active(Api::class))->toBeFalse();

    // Team subscribes to pro — without a purge the stale value sticks.
    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_flag_purge',
        'stripe_status' => 'active',
        'stripe_price' => config('plans.plans.pro.stripe_monthly'),
        'quantity' => 1,
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived([
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['customer' => 'cus_flag_purge']],
    ]));

    expect(Feature::for($team->fresh())->active(Api::class))->toBeTrue();
});
