<?php

use App\Models\Project;
use App\Models\Team;
use App\Support\CurrentTeam;
use Spatie\Activitylog\Models\Activity;

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

test('team changes are written to the activity log', function () {
    $team = Team::factory()->create();

    $team->forceFill(['name' => 'Renamed Co'])->save();

    expect(Activity::forSubject($team)
        ->where('log_name', 'team')
        ->where('description', 'updated')
        ->exists())->toBeTrue();
});

test('project changes are written to the activity log', function () {
    $team = Team::factory()->create();
    app(CurrentTeam::class)->set($team);

    $project = Project::create(['name' => 'Logged project']);
    $project->update(['name' => 'Renamed project']);

    expect(Activity::forSubject($project)->where('description', 'created')->exists())->toBeTrue()
        ->and(Activity::forSubject($project)->where('description', 'updated')->exists())->toBeTrue();
});
