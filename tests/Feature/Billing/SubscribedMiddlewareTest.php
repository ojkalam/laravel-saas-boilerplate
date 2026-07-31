<?php

use App\Http\Middleware\SetCurrentTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', SetCurrentTeam::class, 'subscribed'])
        ->group(function () {
            Route::get('/_test/premium', fn () => response()->json(['ok' => true]));
            Route::post('/_test/premium', fn () => response()->json(['ok' => true]));
        });
});

function userOnTeam(array $teamAttributes = []): User
{
    $user = User::factory()->create();
    $team = Team::factory()->create($teamAttributes);
    $team->members()->attach($user, ['role' => 'owner']);
    $user->forceFill(['current_team_id' => $team->id])->save();

    return $user;
}

test('a trialing team can access premium routes', function () {
    $user = userOnTeam(['trial_ends_at' => now()->addDays(7)]);

    $this->actingAs($user)->get('/_test/premium')->assertOk();
});

test('a team with no trial and no subscription is redirected to pricing', function () {
    $user = userOnTeam(['trial_ends_at' => now()->subDay()]);

    $this->actingAs($user)->get('/_test/premium')->assertRedirect(route('pricing', absolute: false));
});

test('an actively subscribed team can access premium routes', function () {
    $user = userOnTeam(['trial_ends_at' => now()->subDay()]);
    $user->currentTeam->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_middleware_test',
        'stripe_status' => 'active',
        'stripe_price' => config('plans.plans.pro.stripe_monthly'),
        'quantity' => 1,
    ]);

    $this->actingAs($user)->get('/_test/premium')->assertOk();
});

test('a past_due team beyond grace keeps reads but loses writes', function () {
    $user = userOnTeam(['trial_ends_at' => now()->subDay()]);
    $subscription = $user->currentTeam->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_past_due_test',
        'stripe_status' => 'past_due',
        'stripe_price' => config('plans.plans.pro.stripe_monthly'),
        'quantity' => 1,
    ]);
    $subscription->timestamps = false;
    $subscription->forceFill(['updated_at' => now()->subDays(10)])->save();

    $this->actingAs($user)->get('/_test/premium')->assertOk();
    $this->actingAs($user)->post('/_test/premium')->assertStatus(402);
});
