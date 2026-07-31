<?php

use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Livewire\Livewire;

function tokenPageUser(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $user->assignTeamRole($team, 'owner');
    $user->forceFill(['current_team_id' => $team->id])->save();

    app(CurrentTeam::class)->set($team);
    setPermissionsTeamId($team->id);

    return [$user, $team];
}

test('a user can create a team-scoped token from settings', function () {
    [$user, $team] = tokenPageUser();

    $this->actingAs($user);

    $component = Livewire::test('pages::settings.api-tokens')
        ->set('tokenName', 'CI token')
        ->call('createToken')
        ->assertHasNoErrors();

    expect($component->get('plainTextToken'))->not->toBeNull();

    $token = $user->tokens()->firstOrFail();
    expect($token->name)->toBe('CI token')
        ->and($token->abilities)->toBe(['team:'.$team->id]);
});

test('a user can revoke a token', function () {
    [$user, $team] = tokenPageUser();
    $token = $user->createToken('Old token', ['team:'.$team->id]);

    $this->actingAs($user);

    Livewire::test('pages::settings.api-tokens')
        ->call('revokeToken', $token->accessToken->id);

    expect($user->tokens()->count())->toBe(0);
});

test('the api tokens page renders', function () {
    [$user] = tokenPageUser();

    $this->actingAs($user)->get(route('api-tokens.edit'))->assertOk();
});
