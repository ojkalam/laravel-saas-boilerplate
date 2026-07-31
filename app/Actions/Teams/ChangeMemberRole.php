<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ChangeMemberRole
{
    public function handle(Team $team, User $user, string $role): void
    {
        if ($team->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'role' => __('The owner\'s role cannot be changed.'),
            ]);
        }

        if (! in_array($role, [TeamRole::Admin->value, TeamRole::Member->value, TeamRole::Billing->value], true)) {
            throw ValidationException::withMessages([
                'role' => __('Invalid role.'),
            ]);
        }

        if (! $team->hasMember($user)) {
            throw ValidationException::withMessages([
                'role' => __('This user is not a member of the team.'),
            ]);
        }

        $team->members()->updateExistingPivot($user->id, ['role' => $role]);

        $user->removeTeamRoles($team);
        $user->assignTeamRole($team, $role);
    }
}
