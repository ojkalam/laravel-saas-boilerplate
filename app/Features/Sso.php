<?php

namespace App\Features;

use App\Models\Team;

class Sso
{
    public string $name = 'sso';

    public function resolve(Team $team): bool
    {
        return $team->plan()->allows('sso');
    }
}
