<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    public function handle(User $user, string $name, bool $personal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $personal): Team {
            $team = new Team([
                'name' => $name,
                'slug' => Team::generateSlug($name),
                'personal_team' => $personal,
            ]);

            $team->owner()->associate($user);
            $team->save();

            $team->members()->attach($user, ['role' => 'owner']);
            $user->assignTeamRole($team, 'owner');

            if ($user->current_team_id === null) {
                $user->forceFill(['current_team_id' => $team->id])->save();
            }

            return $team;
        });
    }
}
