<?php

namespace App\Support\Usage;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Atomic, per-billing-period usage counters backed by the
 * usage_counters table and its (team_id, metric, period_start)
 * unique index.
 */
class UsageMeter
{
    /**
     * Record consumption for a metric. Safe under concurrency: the
     * insert-or-increment happens in a single statement.
     */
    public function record(Team $team, string $metric, int $amount = 1): void
    {
        $now = now();

        DB::statement(
            'insert into usage_counters (team_id, metric, period_start, value, created_at, updated_at)
             values (?, ?, ?, ?, ?, ?)
             on conflict (team_id, metric, period_start)
             do update set value = usage_counters.value + excluded.value, updated_at = excluded.updated_at',
            [
                $team->id,
                $metric,
                $team->currentPeriodStart(),
                $amount,
                $now,
                $now,
            ],
        );
    }

    /**
     * Consumption of a metric in the current billing period.
     */
    public function usage(Team $team, string $metric): int
    {
        // Queries the table directly (not the scoped model): the team
        // is explicit here and jobs may meter without a bound context.
        return (int) DB::table('usage_counters')
            ->where('team_id', $team->id)
            ->where('metric', $metric)
            ->where('period_start', $team->currentPeriodStart())
            ->value('value');
    }

    /**
     * Whether the team can consume $amount more of a metered metric
     * in the current period.
     */
    public function canConsume(Team $team, string $metric, int $amount = 1): bool
    {
        $plan = $team->plan();

        if ($plan->isUnlimited($metric)) {
            return true;
        }

        $limit = $plan->limit($metric);

        if ($limit === null || $limit <= 0) {
            return false;
        }

        return $this->usage($team, $metric) + $amount <= $limit;
    }
}
