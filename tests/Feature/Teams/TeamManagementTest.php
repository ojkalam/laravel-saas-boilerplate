<?php

use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\RemoveTeamMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('create team attaches the owner and sets current team when none is set', function () {
    $user = User::factory()->create();

    $team = app(CreateTeam::class)->handle($user, 'Acme Inc');

    expect($team->owner_id)->toBe($user->id)
        ->and($team->hasMember($user))->toBeTrue()
        ->and($team->memberRole($user))->toBe('owner')
        ->and($user->fresh()->current_team_id)->toBe($team->id)
        ->and($team->slug)->not->toBeEmpty();
});

test('team slugs are unique even for identical names', function () {
    $user = User::factory()->create();

    $first = app(CreateTeam::class)->handle($user, 'Same Name');
    $second = app(CreateTeam::class)->handle($user, 'Same Name');

    expect($first->slug)->not->toBe($second->slug);
});

test('a member can switch to a team they belong to', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create(['owner_id' => $user->id]);
    $teamB = Team::factory()->create(['owner_id' => $user->id]);
    $user->forceFill(['current_team_id' => $teamA->id])->save();

    $response = $this->actingAs($user)->put('/current-team', ['team_id' => $teamB->id]);

    $response->assertRedirect(route('dashboard', absolute: false));
    expect($user->fresh()->current_team_id)->toBe($teamB->id);
});

test('a user cannot switch to a team they do not belong to', function () {
    $user = User::factory()->create();
    Team::factory()->create(['owner_id' => $user->id]);
    $otherTeam = Team::factory()->create();

    $response = $this->actingAs($user)->put('/current-team', ['team_id' => $otherTeam->id]);

    $response->assertForbidden();
    expect($user->fresh()->current_team_id)->not->toBe($otherTeam->id);
});

test('the team owner cannot be removed from the team', function () {
    $team = Team::factory()->create();

    app(RemoveTeamMember::class)->handle($team, $team->owner);
})->throws(ValidationException::class);

test('removing a member clears their current team pointer', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => 'member']);
    $member->forceFill(['current_team_id' => $team->id])->save();

    app(RemoveTeamMember::class)->handle($team, $member);

    expect($team->fresh()->hasMember($member))->toBeFalse()
        ->and($member->fresh()->current_team_id)->toBeNull();
});

test('the set-current-team middleware heals a stale team pointer', function () {
    $user = User::factory()->create();
    $ownTeam = Team::factory()->create(['owner_id' => $user->id]);
    $foreignTeam = Team::factory()->create();
    $user->forceFill(['current_team_id' => $foreignTeam->id])->save();

    $this->actingAs($user)->get('/dashboard')->assertOk();

    expect($user->fresh()->current_team_id)->toBe($ownTeam->id);
});
