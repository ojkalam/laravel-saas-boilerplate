<?php

namespace App\Actions\Billing;

use App\Models\Team;

/**
 * Keeps the Stripe subscription quantity in line with the team's
 * member count for per-seat plans. Call after members are added or
 * removed; it is a no-op for non-seat plans and unsubscribed teams.
 */
class SyncSeatCount
{
    public function handle(Team $team): void
    {
        if (! $team->hasActiveSubscription() || ! $team->plan()->perSeat) {
            return;
        }

        $team->subscription('default')?->updateQuantity(max(1, $team->members()->count()));
    }
}
