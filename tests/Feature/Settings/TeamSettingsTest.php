<?php

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function ownerOfTeam(): array
{
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $owner->assignTeamRole($team, 'owner');
    $owner->forceFill(['current_team_id' => $team->id])->save();

    return [$owner, $team];
}

function bindTeam(Team $team): void
{
    app(CurrentTeam::class)->set($team);
    setPermissionsTeamId($team->id);
}

function memberOfTeam(Team $team, string $role = 'member'): User
{
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => $role]);
    $member->assignTeamRole($team, $role);
    $member->forceFill(['current_team_id' => $team->id])->save();

    return $member;
}

test('the team settings page renders for a member', function () {
    [$owner] = ownerOfTeam();

    $this->actingAs($owner)->get(route('team.edit'))->assertOk();
});

test('the owner can rename the team', function () {
    [$owner, $team] = ownerOfTeam();

    $this->actingAs($owner);
    bindTeam($team);

    Livewire::test('pages::settings.team')
        ->set('name', 'Renamed Team')
        ->call('updateName')
        ->assertHasNoErrors();

    expect($team->fresh()->name)->toBe('Renamed Team');
});

test('a plain member cannot rename the team', function () {
    [, $team] = ownerOfTeam();
    $member = memberOfTeam($team);

    $this->actingAs($member);
    bindTeam($team);

    Livewire::test('pages::settings.team')
        ->set('name', 'Hacked')
        ->call('updateName')
        ->assertForbidden();

    expect($team->fresh()->name)->not->toBe('Hacked');
});

test('an admin can invite and cancel invitations from the page', function () {
    Mail::fake();

    [, $team] = ownerOfTeam();
    $admin = memberOfTeam($team, 'admin');

    $this->actingAs($admin);
    bindTeam($team);

    Livewire::test('pages::settings.team')
        ->set('inviteEmail', 'newcomer@example.com')
        ->set('inviteRole', 'member')
        ->call('inviteMember')
        ->assertHasNoErrors();

    $invitation = TeamInvitation::where('email', 'newcomer@example.com')->firstOrFail();
    expect($invitation->team_id)->toBe($team->id);

    Livewire::test('pages::settings.team')
        ->call('cancelInvitation', $invitation->id);

    expect(TeamInvitation::whereKey($invitation->id)->exists())->toBeFalse();
});

test('a member cannot invite anyone', function () {
    Mail::fake();

    [, $team] = ownerOfTeam();
    $member = memberOfTeam($team);

    $this->actingAs($member);
    bindTeam($team);

    Livewire::test('pages::settings.team')
        ->set('inviteEmail', 'x@example.com')
        ->call('inviteMember')
        ->assertForbidden();

    Mail::assertNothingSent();
});

test('the owner can change a member role', function () {
    [$owner, $team] = ownerOfTeam();
    $member = memberOfTeam($team);

    $this->actingAs($owner);
    bindTeam($team);

    Livewire::test('pages::settings.team')
        ->call('changeRole', $member->id, 'admin')
        ->assertHasNoErrors();

    expect($team->memberRole($member))->toBe('admin')
        ->and($member->hasTeamPermission($team, 'projects.delete'))->toBeTrue();
});

test('the owner can remove a member from the page', function () {
    [$owner, $team] = ownerOfTeam();
    $member = memberOfTeam($team);

    $this->actingAs($owner);
    bindTeam($team);

    Livewire::test('pages::settings.team')
        ->call('removeMember', $member->id);

    expect($team->fresh()->hasMember($member))->toBeFalse();
});

test('a member can leave the team but the owner cannot', function () {
    [$owner, $team] = ownerOfTeam();
    $member = memberOfTeam($team);

    $this->actingAs($member);
    bindTeam($team);
    Livewire::test('pages::settings.team')->call('leaveTeam');
    expect($team->fresh()->hasMember($member))->toBeFalse();

    $this->actingAs($owner);
    bindTeam($team);
    Livewire::test('pages::settings.team')
        ->call('leaveTeam')
        ->assertHasErrors('team');
    expect($team->fresh()->hasMember($owner))->toBeTrue();
});

test('deleting a team requires the exact confirmation phrase', function () {
    [$owner, $team] = ownerOfTeam();

    $this->actingAs($owner);
    bindTeam($team);

    Livewire::test('pages::settings.team')
        ->set('deleteConfirmation', 'wrong name')
        ->call('deleteTeam')
        ->assertHasErrors('deleteConfirmation');

    expect(Team::whereKey($team->id)->exists())->toBeTrue();

    Livewire::test('pages::settings.team')
        ->set('deleteConfirmation', $team->name)
        ->call('deleteTeam');

    expect(Team::whereKey($team->id)->exists())->toBeFalse();
});

test('a personal team cannot be deleted even by its owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->personal()->create(['owner_id' => $owner->id]);
    $owner->assignTeamRole($team, 'owner');
    $owner->forceFill(['current_team_id' => $team->id])->save();

    $this->actingAs($owner);
    bindTeam($team);

    Livewire::test('pages::settings.team')
        ->set('deleteConfirmation', $team->name)
        ->call('deleteTeam')
        ->assertForbidden();

    expect(Team::whereKey($team->id)->exists())->toBeTrue();
});
