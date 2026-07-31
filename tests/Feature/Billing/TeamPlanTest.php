<?php

use App\Actions\Teams\CreateTeam;
use App\Models\Team;
use App\Models\User;
use Laravel\Cashier\Subscription;

function subscribeTeam(Team $team, string $price, string $status = 'active'): Subscription
{
    return $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->lexify('??????????'),
        'stripe_status' => $status,
        'stripe_price' => $price,
        'quantity' => 1,
    ]);
}

test('a new team starts on a no-card trial of the trial plan', function () {
    $user = User::factory()->create();

    $team = app(CreateTeam::class)->handle($user, 'Trial Co');

    expect($team->onGenericTrial())->toBeTrue()
        ->and($team->trial_ends_at->isFuture())->toBeTrue()
        ->and($team->plan()->key)->toBe('pro');
});

test('a team with an expired trial and no subscription falls back to the free plan', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);

    expect($team->onGenericTrial())->toBeFalse()
        ->and($team->plan()->key)->toBe('free')
        ->and($team->hasActiveSubscription())->toBeFalse();
});

test('a subscribed team resolves its plan from the stripe price', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    subscribeTeam($team, config('plans.plans.enterprise.stripe_monthly'));

    expect($team->hasActiveSubscription())->toBeTrue()
        ->and($team->plan()->key)->toBe('enterprise');
});

test('a canceled-and-expired subscription no longer counts', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $subscription = subscribeTeam($team, config('plans.plans.pro.stripe_monthly'), 'canceled');
    $subscription->update(['ends_at' => now()->subDay()]);

    expect($team->hasActiveSubscription())->toBeFalse()
        ->and($team->plan()->key)->toBe('free');
});

test('a past_due team becomes read-only only after the grace period', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $subscription = subscribeTeam($team, config('plans.plans.pro.stripe_monthly'), 'past_due');

    expect($team->isReadOnly())->toBeFalse();

    $subscription->timestamps = false;
    $subscription->forceFill(['updated_at' => now()->subDays(10)])->save();

    expect($team->fresh()->isReadOnly())->toBeTrue();
});
