<?php

use App\Support\Plans\Plan;
use App\Support\Plans\PlanRegistry;
use Tests\TestCase;

uses(TestCase::class);

test('all plans are exposed as plan objects', function () {
    $plans = app(PlanRegistry::class)->all();

    expect($plans->keys()->all())->toBe(['free', 'pro', 'enterprise'])
        ->and($plans->every(fn ($plan) => $plan instanceof Plan))->toBeTrue();
});

test('a plan can be found by key and unknown keys throw', function () {
    $pro = app(PlanRegistry::class)->find('pro');

    expect($pro->name)->toBe('Pro')
        ->and($pro->perSeat)->toBeTrue()
        ->and(fn () => app(PlanRegistry::class)->find('nope'))
        ->toThrow(InvalidArgumentException::class);
});

test('plans resolve from stripe price ids', function () {
    $registry = app(PlanRegistry::class);

    expect($registry->fromStripePrice(config('plans.plans.pro.stripe_monthly'))?->key)->toBe('pro')
        ->and($registry->fromStripePrice(config('plans.plans.enterprise.stripe_yearly'))?->key)->toBe('enterprise')
        ->and($registry->fromStripePrice('price_unknown'))->toBeNull();
});

test('feature and limit lookups behave for boolean, numeric, unlimited, and unknown values', function () {
    $registry = app(PlanRegistry::class);
    $free = $registry->find('free');
    $enterprise = $registry->find('enterprise');

    expect($free->allows('audit_log'))->toBeFalse()
        ->and($free->allows('does_not_exist'))->toBeFalse()
        ->and($free->limit('projects'))->toBe(3)
        ->and($free->limit('does_not_exist'))->toBe(0)
        ->and($enterprise->limit('seats'))->toBeNull()
        ->and($enterprise->isUnlimited('seats'))->toBeTrue()
        ->and($free->isUnlimited('seats'))->toBeFalse();
});

test('default and trial plans come from config', function () {
    $registry = app(PlanRegistry::class);

    expect($registry->default()->key)->toBe('free')
        ->and($registry->trialPlan()->key)->toBe('pro');
});
