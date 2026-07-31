<?php

namespace App\Support;

use App\Models\Team;

/**
 * Holds the team context for the current request / job.
 *
 * Registered as a scoped singleton so the binding is reset between
 * requests (Octane) and between queued jobs. Never resolve the team
 * from request input — only from the authenticated user or an
 * explicitly provided team (jobs, console commands).
 */
class CurrentTeam
{
    protected ?Team $team = null;

    public function set(?Team $team): void
    {
        $this->team = $team;
    }

    public function forget(): void
    {
        $this->team = null;
    }

    public function id(): ?int
    {
        return $this->team?->id;
    }

    public function model(): ?Team
    {
        return $this->team;
    }

    public function check(): bool
    {
        return $this->team !== null;
    }
}
