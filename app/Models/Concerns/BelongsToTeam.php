<?php

namespace App\Models\Concerns;

use App\Models\Team;
use App\Support\CurrentTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query on the model to the current team and stamps
 * team_id automatically on create. Apply to every tenant-owned model.
 */
trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $query): void {
            if ($teamId = app(CurrentTeam::class)->id()) {
                $query->where($query->getModel()->qualifyColumn('team_id'), $teamId);
            }
        });

        static::creating(function (self $model): void {
            $model->team_id ??= app(CurrentTeam::class)->id();
        });
    }

    /**
     * Deliberately query across every team.
     *
     * The only sanctioned way to leave the tenant scope. Use it where
     * there is genuinely no current team — Stripe webhooks, queued
     * jobs, console commands, key-authenticated API calls, and the
     * staff back-office — and nowhere else. CI greps for raw
     * withoutGlobalScope calls so that exceptions stay visible here.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAcrossTeams(Builder $query): void
    {
        $query->withoutGlobalScope('team');
    }

    /**
     * @return BelongsTo<Team, covariant $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
