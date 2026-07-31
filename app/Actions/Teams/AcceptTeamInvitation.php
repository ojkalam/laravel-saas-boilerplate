<?php

namespace App\Actions\Teams;

use App\Actions\Billing\SyncSeatCount;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptTeamInvitation
{
    public function handle(User $user, TeamInvitation $invitation): void
    {
        if ($invitation->hasExpired()) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation has expired.'),
            ]);
        }

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation was sent to a different email address.'),
            ]);
        }

        $team = $invitation->team;

        DB::transaction(function () use ($user, $invitation, $team): void {
            if (! $team->hasMember($user)) {
                $team->members()->attach($user, ['role' => $invitation->role]);
                $user->assignTeamRole($team, $invitation->role);
            }

            $invitation->delete();

            $user->switchTeam($team);
        });

        app(SyncSeatCount::class)->handle($team->fresh());
    }
}
