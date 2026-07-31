<?php

namespace App\Http\Middleware;

use App\Support\CurrentTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role check against the current team, e.g.:
 *
 *     Route::middleware('team.role:owner,admin')->...
 */
class RequiresRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $team = app(CurrentTeam::class)->model();

        if ($user === null || $team === null) {
            abort(403);
        }

        if ($user->is_staff) {
            return $next($request);
        }

        if (! in_array($user->teamRole($team), $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
