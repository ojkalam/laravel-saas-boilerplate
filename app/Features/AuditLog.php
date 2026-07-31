<?php

namespace App\Features;

use App\Models\Team;

class AuditLog
{
    public string $name = 'audit_log';

    public function resolve(Team $team): bool
    {
        return $team->plan()->allows('audit_log');
    }
}
