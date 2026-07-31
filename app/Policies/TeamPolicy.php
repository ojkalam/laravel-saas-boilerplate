<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'team.view');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'team.update');
    }

    /**
     * Deleting a team is reserved for its owner; personal teams can
     * never be deleted.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) && ! $team->personal_team;
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'team.members.manage');
    }

    public function manageBilling(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'team.billing.manage');
    }
}
