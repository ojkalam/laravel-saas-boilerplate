<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        $team = app(CurrentTeam::class)->model();

        return $team !== null && $user->hasTeamPermission($team, 'projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->allows($user, $project, 'projects.view');
    }

    public function create(User $user): bool
    {
        $team = app(CurrentTeam::class)->model();

        return $team !== null && $user->hasTeamPermission($team, 'projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->allows($user, $project, 'projects.update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->allows($user, $project, 'projects.delete');
    }

    /**
     * The permission must hold on the team that owns the record — not
     * merely whatever team happens to be current.
     */
    protected function allows(User $user, Project $project, string $permission): bool
    {
        $team = $project->team;

        return $team instanceof Team && $user->hasTeamPermission($team, $permission);
    }
}
