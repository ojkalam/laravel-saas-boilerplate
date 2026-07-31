<?php

use App\Actions\Teams\AcceptTeamInvitation;
use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\RemoveTeamMember;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('the seeder creates every role with its expected permissions', function () {
    foreach (TeamRole::cases() as $role) {
        $model = Role::findByName($role->value);

        expect($model->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect($role->permissions())->sort()->values()->all());
    }
});

test('creating a team grants the creator the owner role scoped to that team', function () {
    $user = User::factory()->create();

    $team = app(CreateTeam::class)->handle($user, 'Acme');

    expect($user->hasTeamPermission($team, 'team.delete'))->toBeTrue()
        ->and($user->hasTeamPermission($team, 'team.billing.manage'))->toBeTrue();
});

test('accepting an invitation grants the invited role scoped to that team', function () {
    $team = Team::factory()->create();
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invitee@example.com',
        'role' => 'member',
    ]);
    $user = User::factory()->create(['email' => 'invitee@example.com']);

    app(AcceptTeamInvitation::class)->handle($user, $invitation);

    expect($user->hasTeamPermission($team, 'projects.create'))->toBeTrue()
        ->and($user->hasTeamPermission($team, 'team.members.manage'))->toBeFalse()
        ->and($user->hasTeamPermission($team, 'projects.delete'))->toBeFalse();
});

test('permissions are scoped per team, not global', function () {
    $user = User::factory()->create();

    $ownedTeam = app(CreateTeam::class)->handle($user, 'Owned');

    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => 'member']);
    $user->assignTeamRole($otherTeam, 'member');

    expect($user->hasTeamPermission($ownedTeam, 'team.update'))->toBeTrue()
        ->and($user->hasTeamPermission($otherTeam, 'team.update'))->toBeFalse();
});

test('a user has no permissions on a team they do not belong to', function () {
    $user = User::factory()->create();
    $foreignTeam = Team::factory()->create();

    expect($user->hasTeamPermission($foreignTeam, 'team.view'))->toBeFalse();
});

test('removing a member also removes their team-scoped roles', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => 'admin']);
    $member->assignTeamRole($team, 'admin');

    app(RemoveTeamMember::class)->handle($team, $member);

    expect($member->hasTeamPermission($team, 'projects.delete'))->toBeFalse()
        ->and($member->roles()->count())->toBe(0);
});
