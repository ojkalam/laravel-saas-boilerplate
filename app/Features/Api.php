<?php

namespace App\Features;

use App\Models\Team;

class Api
{
    public string $name = 'api';

    public function resolve(Team $team): bool
    {
        return $team->plan()->allows('api');
    }
}
