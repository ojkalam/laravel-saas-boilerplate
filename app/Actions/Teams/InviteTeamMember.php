<?php

namespace App\Actions\Teams;

use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteTeamMember
{
    public function handle(Team $team, string $email, string $role): TeamInvitation
    {
        $email = Str::lower($email);

        if ($team->members()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This user is already a member of the team.'),
            ]);
        }

        if ($team->invitations()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('An invitation for this email is already pending.'),
            ]);
        }

        $invitation = $team->invitations()->create([
            'email' => $email,
            'role' => $role,
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new TeamInvitationMail($invitation));

        return $invitation;
    }
}
