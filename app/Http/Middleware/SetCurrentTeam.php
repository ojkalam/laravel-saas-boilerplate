<?php

namespace App\Http\Middleware;

use App\Support\CurrentTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current team once per request from the authenticated
 * user's current_team_id — never from request input — and binds it
 * into the CurrentTeam singleton.
 */
class SetCurrentTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $team = $user->currentTeam;

            // Heal a stale or missing pointer: fall back to the first
            // team the user still belongs to and persist the fix.
            if ($team === null || ! $user->belongsToTeam($team)) {
                $team = $user->teams()->orderByPivot('created_at')->first();

                if ($team !== null) {
                    $user->forceFill(['current_team_id' => $team->id])->save();
                } elseif ($user->current_team_id !== null) {
                    $user->forceFill(['current_team_id' => null])->save();
                    $team = null;
                }
            }

            app(CurrentTeam::class)->set($team);
        }

        return $next($request);
    }
}
