<?php

namespace App\Jobs;

use App\Jobs\Concerns\TenantAware;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Base class for every job that touches tenant-owned models. The
 * team context is bound before execute() runs and always cleared
 * afterwards, so global scopes behave exactly as they do in requests.
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, Queueable, TenantAware;

    public function __construct(Team|int $team)
    {
        $this->forTeam($team);
    }

    public function handle(): void
    {
        $this->bindTeamContext();

        try {
            $this->execute();
        } finally {
            $this->forgetTeamContext();
        }
    }

    abstract protected function execute(): void;
}
