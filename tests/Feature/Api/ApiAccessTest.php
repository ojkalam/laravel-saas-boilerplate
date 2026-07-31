<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\User;

function bearer(User $user, Team $team): array
{
    // The auth guard caches the resolved user per test; forget it so
    // each bearer token authenticates independently.
    app('auth')->forgetGuards();

    $plain = $user->createToken('test-token', ['team:'.$team->id])->plainTextToken;

    return ['Authorization' => 'Bearer '.$plain];
}

function trialTeamWithMember(string $role = 'member'): array
{
    $team = Team::factory()->create(['trial_ends_at' => now()->addDays(7)]);
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role]);
    $user->assignTeamRole($team, $role);

    return [$user, $team];
}

test('a team-scoped token can read its own team through the api', function () {
    [$user, $team] = trialTeamWithMember();

    $this->getJson('/api/v1/me', bearer($user, $team))
        ->assertOk()
        ->assertJsonPath('data.team.id', $team->id)
        ->assertJsonPath('data.plan', 'pro');
});

test('the api only exposes the token team projects', function () {
    [$user, $team] = trialTeamWithMember();
    $otherTeam = Team::factory()->create(['trial_ends_at' => now()->addDays(7)]);

    Project::factory()->create(['team_id' => $team->id, 'name' => 'Mine']);
    Project::factory()->create(['team_id' => $otherTeam->id, 'name' => 'Not mine']);

    $response = $this->getJson('/api/v1/projects', bearer($user, $team))->assertOk();

    expect(collect($response->json('data.data'))->pluck('name')->all())->toBe(['Mine']);
});

test('a token for a team the user does not belong to is rejected', function () {
    [$user] = trialTeamWithMember();
    $foreignTeam = Team::factory()->create(['trial_ends_at' => now()->addDays(7)]);

    $this->getJson('/api/v1/me', bearer($user, $foreignTeam))->assertForbidden();
});

test('a token without a team ability is rejected', function () {
    [$user] = trialTeamWithMember();

    $wildcard = $user->createToken('wildcard')->plainTextToken;

    $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$wildcard])->assertForbidden();
});

test('free plans are denied api access', function () {
    $team = Team::factory()->create(['trial_ends_at' => now()->subDay()]);
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->assignTeamRole($team, 'owner');

    $this->getJson('/api/v1/me', bearer($user, $team))
        ->assertForbidden();
});

test('api calls are metered against the monthly quota', function () {
    [$user, $team] = trialTeamWithMember();

    $headers = bearer($user, $team);

    $this->getJson('/api/v1/me', $headers)->assertOk();

    expect($team->usage('api_calls'))->toBe(1);

    // Exhaust the quota; the next call is rejected with 429.
    $team->recordUsage('api_calls', 100_000);

    $this->getJson('/api/v1/me', $headers)->assertStatus(429);
});

test('a member can create projects via the api but billing cannot', function () {
    [$member, $team] = trialTeamWithMember();

    $this->postJson('/api/v1/projects', ['name' => 'Via API'], bearer($member, $team))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Via API');

    expect(Project::withoutGlobalScope('team')->where('team_id', $team->id)->count())->toBe(1);

    $billing = User::factory()->create();
    $team->members()->attach($billing, ['role' => 'billing']);
    $billing->assignTeamRole($team, 'billing');

    $this->postJson('/api/v1/projects', ['name' => 'Denied'], bearer($billing, $team))->assertForbidden();
});

test('a member cannot delete a project via the api but an admin can', function () {
    [$member, $team] = trialTeamWithMember();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => 'admin']);
    $admin->assignTeamRole($team, 'admin');

    $project = Project::factory()->create(['team_id' => $team->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}", [], bearer($member, $team))->assertForbidden();

    $this->deleteJson("/api/v1/projects/{$project->id}", [], bearer($admin, $team))->assertNoContent();
});

test('the per-plan rate limit returns 429 when exceeded', function () {
    config(['plans.plans.pro.limits.api_rate_per_minute' => 2]);

    [$user, $team] = trialTeamWithMember();

    $headers = bearer($user, $team);

    $this->getJson('/api/v1/me', $headers)->assertOk();
    $this->getJson('/api/v1/me', $headers)->assertOk();
    $this->getJson('/api/v1/me', $headers)->assertStatus(429);
});

test('guests get 401 from the api', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
