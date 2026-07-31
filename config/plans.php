<?php

/*
|--------------------------------------------------------------------------
| Plan registry
|--------------------------------------------------------------------------
|
| The single source of truth for plans, their Stripe prices, and their
| entitlements. Adding a plan means editing this file — nothing else.
| Call sites never read this directly; they go through PlanRegistry
| (limits/features) or Pennant feature definitions.
|
*/

return [

    // Plan applied when a team has no subscription and no trial.
    'default' => 'free',

    // Plan whose entitlements apply during the no-card trial.
    'trial_plan' => 'pro',

    // Length of the no-card trial that every new team starts with.
    'trial_days' => 14,

    // Days a past_due team keeps write access before degrading to read-only.
    'grace_period_days' => 7,

    'plans' => [

        'free' => [
            'name' => 'Free',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'per_seat' => false,
            'limits' => [
                'seats' => 1,
                'projects' => 3,
                'api_calls' => 1_000,
                'api_rate_per_minute' => 10,
            ],
            'features' => [
                'sso' => false,
                'audit_log' => false,
                'api' => false,
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'monthly_price' => 29,
            'yearly_price' => 290,
            'stripe_monthly' => env('STRIPE_PRICE_PRO_MONTHLY', 'price_pro_monthly'),
            'stripe_yearly' => env('STRIPE_PRICE_PRO_YEARLY', 'price_pro_yearly'),
            'per_seat' => true,
            'limits' => [
                'seats' => 10,
                'projects' => 50,
                'api_calls' => 100_000,
                'api_rate_per_minute' => 60,
            ],
            'features' => [
                'sso' => false,
                'audit_log' => true,
                'api' => true,
            ],
        ],

        'enterprise' => [
            'name' => 'Enterprise',
            'monthly_price' => 99,
            'yearly_price' => 990,
            'stripe_monthly' => env('STRIPE_PRICE_ENTERPRISE_MONTHLY', 'price_enterprise_monthly'),
            'stripe_yearly' => env('STRIPE_PRICE_ENTERPRISE_YEARLY', 'price_enterprise_yearly'),
            'per_seat' => true,
            'limits' => [
                'seats' => null, // unlimited
                'projects' => null,
                'api_calls' => 1_000_000,
                'api_rate_per_minute' => 240,
            ],
            'features' => [
                'sso' => true,
                'audit_log' => true,
                'api' => true,
            ],
        ],

    ],

];
