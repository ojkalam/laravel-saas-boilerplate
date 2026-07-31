<?php

use App\Models\Concerns\BelongsToTeam;
use App\Models\Project;
use App\Models\Team;
use App\Support\CurrentTeam;
use Illuminate\Support\Facades\File;

/**
 * Every model using the BelongsToTeam trait, discovered from the
 * filesystem so new tenant models are covered automatically.
 *
 * @return array<int, class-string>
 */
function tenantModelClasses(): array
{
    return collect(File::allFiles(app_path('Models')))
        ->map(function ($file) {
            return 'App\\Models\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );
        })
        ->filter(fn (string $class) => class_exists($class)
            && in_array(BelongsToTeam::class, class_uses_recursive($class), true))
        ->values()
        ->all();
}

afterEach(function () {
    app(CurrentTeam::class)->forget();
});

test('tenant models are discovered for leakage testing', function () {
    expect(tenantModelClasses())->not->toBeEmpty()
        ->and(tenantModelClasses())->toContain(Project::class);
});

test('every tenant model is invisible to other teams', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    foreach (tenantModelClasses() as $class) {
        app(CurrentTeam::class)->set($teamA);
        $record = $class::factory()->create(['team_id' => $teamA->id]);

        // Team A sees its own record.
        expect($class::query()->whereKey($record->getKey())->exists())
            ->toBeTrue("[$class] owner team cannot see its own record");

        // Team B must not see it — by key, or at all.
        app(CurrentTeam::class)->set($teamB);
        expect($class::query()->whereKey($record->getKey())->exists())
            ->toBeFalse("[$class] leaked across teams")
            ->and($class::query()->count())
            ->toBe(0, "[$class] leaked rows into another team's listing");
    }
});

test('creating a tenant model stamps the current team automatically', function () {
    $team = Team::factory()->create();
    app(CurrentTeam::class)->set($team);

    $project = Project::create(['name' => 'Stamped']);

    expect($project->team_id)->toBe($team->id);
});

test('a tenant model cannot be re-stamped onto another team implicitly', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    app(CurrentTeam::class)->set($teamA);
    $project = Project::create(['name' => 'Mine']);

    // Switching context does not let team B update team A's record.
    app(CurrentTeam::class)->set($teamB);
    expect(Project::query()->whereKey($project->id)->update(['name' => 'Stolen']))->toBe(0);

    app(CurrentTeam::class)->set($teamA);
    expect($project->fresh()->name)->toBe('Mine');
});

test('queries are unscoped when no team is bound', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    Project::factory()->create(['team_id' => $teamA->id]);
    Project::factory()->create(['team_id' => $teamB->id]);

    app(CurrentTeam::class)->forget();

    expect(Project::query()->count())->toBe(2);
});
