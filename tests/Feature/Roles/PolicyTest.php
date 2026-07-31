<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Support\Facades\Gate;

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

function memberWithRole(Team $team, string $role): User
{
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role]);
    $user->assignTeamRole($team, $role);

    return $user;
}

test('project policy enforces role permissions', function (string $role, array $expected) {
    $team = Team::factory()->create();
    $user = memberWithRole($team, $role);

    app(CurrentTeam::class)->set($team);
    $project = Project::factory()->create(['team_id' => $team->id]);

    expect(Gate::forUser($user)->allows('viewAny', Project::class))->toBe($expected['viewAny'])
        ->and(Gate::forUser($user)->allows('create', Project::class))->toBe($expected['create'])
        ->and(Gate::forUser($user)->allows('update', $project))->toBe($expected['update'])
        ->and(Gate::forUser($user)->allows('delete', $project))->toBe($expected['delete']);
})->with([
    'owner' => ['owner', ['viewAny' => true, 'create' => true, 'update' => true, 'delete' => true]],
    'admin' => ['admin', ['viewAny' => true, 'create' => true, 'update' => true, 'delete' => true]],
    'member' => ['member', ['viewAny' => true, 'create' => true, 'update' => true, 'delete' => false]],
    'billing' => ['billing', ['viewAny' => true, 'create' => false, 'update' => false, 'delete' => false]],
]);

test('team policy enforces role permissions', function (string $role, array $expected) {
    $team = Team::factory()->create();
    $user = memberWithRole($team, $role);

    expect(Gate::forUser($user)->allows('update', $team))->toBe($expected['update'])
        ->and(Gate::forUser($user)->allows('manageMembers', $team))->toBe($expected['manageMembers'])
        ->and(Gate::forUser($user)->allows('manageBilling', $team))->toBe($expected['manageBilling'])
        ->and(Gate::forUser($user)->allows('delete', $team))->toBe($expected['delete']);
})->with([
    'admin' => ['admin', ['update' => true, 'manageMembers' => true, 'manageBilling' => false, 'delete' => false]],
    'member' => ['member', ['update' => false, 'manageMembers' => false, 'manageBilling' => false, 'delete' => false]],
    'billing' => ['billing', ['update' => false, 'manageMembers' => false, 'manageBilling' => true, 'delete' => false]],
]);

test('only the owner can delete a team, and never a personal team', function () {
    $team = Team::factory()->create();

    expect(Gate::forUser($team->owner)->allows('delete', $team))->toBeTrue();

    $personal = Team::factory()->personal()->create();
    expect(Gate::forUser($personal->owner)->allows('delete', $personal))->toBeFalse();
});

test('a policy cannot be bypassed by pointing the current team elsewhere', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $memberOfB = memberWithRole($teamB, 'admin');

    app(CurrentTeam::class)->set($teamA);
    $projectInA = Project::factory()->create(['team_id' => $teamA->id]);

    // Even with team B's admin bound as "current", the record belongs
    // to team A and must stay untouchable.
    app(CurrentTeam::class)->set($teamB);
    expect(Gate::forUser($memberOfB)->allows('update', $projectInA))->toBeFalse()
        ->and(Gate::forUser($memberOfB)->allows('delete', $projectInA))->toBeFalse();
});

test('staff users bypass authorization via the Gate::before hook', function () {
    $staff = User::factory()->staff()->create();
    $team = Team::factory()->create();

    expect(Gate::forUser($staff)->allows('update', $team))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('delete', $team))->toBeTrue();
});
