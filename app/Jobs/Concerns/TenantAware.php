<?php

namespace App\Jobs\Concerns;

use App\Models\Team;
use App\Support\CurrentTeam;

/**
 * Queued jobs and console commands have no authenticated user, so the
 * CurrentTeam singleton is empty when they run. Any job touching
 * tenant-owned models must carry its team and re-bind the context
 * before doing work. Prefer extending TenantAwareJob; use this trait
 * directly only when you cannot extend the base class.
 */
trait TenantAware
{
    public int $tenantTeamId;

    public function forTeam(Team|int $team): static
    {
        $this->tenantTeamId = $team instanceof Team ? $team->id : $team;

        return $this;
    }

    protected function bindTeamContext(): void
    {
        app(CurrentTeam::class)->set(Team::findOrFail($this->tenantTeamId));
    }

    protected function forgetTeamContext(): void
    {
        app(CurrentTeam::class)->forget();
    }
}
