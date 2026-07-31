<?php

use App\Models\Team;

function proTeam(): Team
{
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);

    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_usage_'.fake()->unique()->lexify('??????'),
        'stripe_status' => 'active',
        'stripe_price' => config('plans.plans.pro.stripe_monthly'),
        'quantity' => 1,
    ]);

    return $team;
}

test('usage starts at zero and accumulates atomically', function () {
    $team = proTeam();

    expect($team->usage('api_calls'))->toBe(0);

    $team->recordUsage('api_calls');
    $team->recordUsage('api_calls', 41);

    expect($team->usage('api_calls'))->toBe(42);
});

test('usage is isolated per team and per metric', function () {
    $teamA = proTeam();
    $teamB = proTeam();

    $teamA->recordUsage('api_calls', 10);
    $teamA->recordUsage('exports', 3);

    expect($teamA->usage('api_calls'))->toBe(10)
        ->and($teamA->usage('exports'))->toBe(3)
        ->and($teamB->usage('api_calls'))->toBe(0);
});

test('canConsume respects the plan limit', function () {
    $team = proTeam(); // pro: 100k api calls

    expect($team->canConsume('api_calls'))->toBeTrue();

    $team->recordUsage('api_calls', 100_000);

    expect($team->canConsume('api_calls'))->toBeFalse()
        ->and($team->canConsume('unknown_metric'))->toBeFalse();
});

test('unlimited metrics always allow consumption', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_usage_unlimited',
        'stripe_status' => 'active',
        'stripe_price' => config('plans.plans.enterprise.stripe_monthly'),
        'quantity' => 1,
    ]);

    $team->recordUsage('projects', 1_000_000);

    expect($team->canConsume('projects'))->toBeTrue();
});

test('counters reset with a new billing period', function () {
    $team = proTeam();

    $team->recordUsage('api_calls', 500);
    expect($team->usage('api_calls'))->toBe(500);

    $this->travel(1)->months();

    expect($team->usage('api_calls'))->toBe(0);

    $team->recordUsage('api_calls', 7);
    expect($team->usage('api_calls'))->toBe(7);
});

test('the billing period anchors on the subscription, not the calendar month', function () {
    $this->freezeTime();

    $team = proTeam();
    $subscription = $team->subscription('default');
    $subscription->timestamps = false;
    $subscription->forceFill(['created_at' => now()->subDays(45)])->save();

    $periodStart = $team->fresh()->currentPeriodStart();

    // 45 days after the anchor we are 15 days into the second cycle.
    // Compare at second precision — the anchor did a DB round-trip.
    expect($periodStart->timestamp)->toBe(now()->subDays(45)->addMonth()->timestamp);
});

test('stock limits are checked against the plan', function () {
    $team = proTeam(); // pro: 50 projects, 10 seats

    expect($team->withinPlanLimit('projects', 50))->toBeTrue()
        ->and($team->withinPlanLimit('projects', 51))->toBeFalse()
        ->and($team->withinPlanLimit('seats', 10))->toBeTrue()
        ->and($team->withinPlanLimit('seats', 11))->toBeFalse();
});
