<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeaveTeam
{
    public function __construct(protected RemoveTeamMember $removeTeamMember) {}

    public function handle(Team $team, User $user): void
    {
        if ($team->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'team' => __('The owner cannot leave the team. Transfer ownership or delete the team instead.'),
            ]);
        }

        $this->removeTeamMember->handle($team, $user);
    }
}
