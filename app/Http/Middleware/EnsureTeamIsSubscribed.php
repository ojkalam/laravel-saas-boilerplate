<?php

namespace App\Http\Middleware;

use App\Support\CurrentTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects routes that require a paying (or trialing) team.
 *
 * - No subscription and no trial: redirect to the pricing page.
 * - past_due beyond the grace period: reads still work, writes 402.
 */
class EnsureTeamIsSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $team = app(CurrentTeam::class)->model();

        if ($team === null) {
            return redirect()->route('pricing');
        }

        // past_due teams are not cut off: full access during the grace
        // period, read-only afterwards (dunning, not a hard lock).
        $pastDue = $team->subscription('default')?->stripe_status === 'past_due';

        if (! $team->hasActiveSubscription() && ! $team->onGenericTrial() && ! $pastDue) {
            return redirect()->route('pricing');
        }

        if ($team->isReadOnly() && ! $request->isMethodSafe()) {
            abort(402, __('Your subscription is past due. Update your payment method to regain write access.'));
        }

        return $next($request);
    }
}
