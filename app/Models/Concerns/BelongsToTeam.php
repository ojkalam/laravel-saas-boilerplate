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
     * @return BelongsTo<Team, covariant $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
