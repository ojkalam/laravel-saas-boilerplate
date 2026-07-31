<?php

use App\Actions\Teams\InviteTeamMember;
use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

test('inviting a member creates an invitation and emails a signed link', function () {
    Mail::fake();

    $team = Team::factory()->create();

    $invitation = app(InviteTeamMember::class)->handle($team, 'invitee@example.com', 'member');

    expect($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->role)->toBe('member')
        ->and($invitation->expires_at->isFuture())->toBeTrue();

    Mail::assertSent(TeamInvitationMail::class, fn (TeamInvitationMail $mail) => $mail->hasTo('invitee@example.com')
        && $mail->invitation->is($invitation));
});

test('an existing member cannot be invited again', function () {
    Mail::fake();

    $team = Team::factory()->create();

    app(InviteTeamMember::class)->handle($team, $team->owner->email, 'member');
})->throws(ValidationException::class);

test('a pending invitation cannot be duplicated', function () {
    Mail::fake();

    $team = Team::factory()->create();
    TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

    app(InviteTeamMember::class)->handle($team, 'invitee@example.com', 'member');
})->throws(ValidationException::class);

test('an invited user can accept via the signed link', function () {
    $team = Team::factory()->create();
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invitee@example.com',
    ]);
    $user = User::factory()->create(['email' => 'invitee@example.com']);

    $url = URL::temporarySignedRoute(
        'team-invitations.accept',
        $invitation->expires_at,
        ['invitation' => $invitation],
    );

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard', absolute: false));

    expect($team->fresh()->hasMember($user))->toBeTrue()
        ->and($team->memberRole($user))->toBe('member')
        ->and($user->fresh()->current_team_id)->toBe($team->id)
        ->and(TeamInvitation::query()->whereKey($invitation->id)->exists())->toBeFalse();
});

test('the accept link is rejected without a valid signature', function () {
    $invitation = TeamInvitation::factory()->create();
    $user = User::factory()->create(['email' => $invitation->email]);

    $this->actingAs($user)
        ->get(route('team-invitations.accept', $invitation))
        ->assertForbidden();
});

test('an expired invitation cannot be accepted', function () {
    $invitation = TeamInvitation::factory()->create([
        'expires_at' => now()->addMinute(),
    ]);
    $user = User::factory()->create(['email' => $invitation->email]);

    $url = URL::temporarySignedRoute(
        'team-invitations.accept',
        $invitation->expires_at,
        ['invitation' => $invitation],
    );

    $this->travel(10)->minutes();

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($invitation->team->fresh()->hasMember($user))->toBeFalse();
});

test('an invitation cannot be accepted by a different email address', function () {
    $invitation = TeamInvitation::factory()->create(['email' => 'invitee@example.com']);
    $stranger = User::factory()->create(['email' => 'stranger@example.com']);

    $url = URL::temporarySignedRoute(
        'team-invitations.accept',
        $invitation->expires_at,
        ['invitation' => $invitation],
    );

    $this->actingAs($stranger)->get($url)->assertRedirect(route('dashboard', absolute: false));

    expect($invitation->team->hasMember($stranger))->toBeFalse()
        ->and(TeamInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
});
