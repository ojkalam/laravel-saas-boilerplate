<?php

namespace App\Actions\Teams;

use App\Models\Team;

class UpdateTeamName
{
    public function handle(Team $team, string $name): Team
    {
        $team->forceFill(['name' => $name])->save();

        return $team;
    }
}
