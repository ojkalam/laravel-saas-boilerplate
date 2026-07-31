<?php

namespace App\Actions\Teams;

use App\Actions\Billing\SyncSeatCount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RemoveTeamMember
{
    public function handle(Team $team, User $user): void
    {
        if ($team->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'member' => __('The team owner cannot be removed from the team.'),
            ]);
        }

        $team->members()->detach($user);
        $user->removeTeamRoles($team);

        if ($user->current_team_id === $team->id) {
            $fallback = $user->teams()->orderByPivot('created_at')->first();
            $user->forceFill(['current_team_id' => $fallback?->id])->save();
        }

        app(SyncSeatCount::class)->handle($team->fresh());
    }
}
