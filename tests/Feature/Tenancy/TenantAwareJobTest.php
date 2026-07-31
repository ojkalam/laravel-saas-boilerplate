<?php

use App\Jobs\TenantAwareJob;
use App\Models\Project;
use App\Models\Team;
use App\Support\CurrentTeam;

class CreateProjectTestJob extends TenantAwareJob
{
    protected function execute(): void
    {
        Project::create(['name' => 'From job']);
    }
}

test('a tenant-aware job binds the team context while running', function () {
    $team = Team::factory()->create();

    (new CreateProjectTestJob($team))->handle();

    expect(Project::withoutGlobalScope('team')->where('team_id', $team->id)->count())->toBe(1)
        ->and(app(CurrentTeam::class)->check())->toBeFalse();
});

test('a tenant-aware job clears the context even when it fails', function () {
    $team = Team::factory()->create();

    $job = new class($team) extends TenantAwareJob
    {
        protected function execute(): void
        {
            throw new RuntimeException('boom');
        }
    };

    expect(fn () => $job->handle())->toThrow(RuntimeException::class)
        ->and(app(CurrentTeam::class)->check())->toBeFalse();
});

test('a tenant-aware job only sees its own team data', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    Project::factory()->create(['team_id' => $teamA->id]);
    Project::factory()->create(['team_id' => $teamB->id]);

    $job = new class($teamA) extends TenantAwareJob
    {
        public int $visible = 0;

        protected function execute(): void
        {
            $this->visible = Project::query()->count();
        }
    };

    $job->handle();

    expect($job->visible)->toBe(1);
});
