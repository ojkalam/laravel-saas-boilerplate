<?php

namespace App\Http\Middleware;

use App\Features\Api;
use App\Models\Team;
use App\Support\CurrentTeam;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * API tokens are scoped to exactly one team via a `team:{id}` token
 * ability. This middleware resolves that team, re-verifies membership
 * (a token must die when its owner leaves the team), binds the team
 * context, enforces the plan's API feature flag and monthly quota,
 * and meters usage.
 */
class SetTeamFromApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user === null || $token === null) {
            abort(401);
        }

        $team = $this->resolveTeam($token->abilities ?? []);

        if ($team === null || ! $user->belongsToTeam($team)) {
            abort(403, __('This token is not valid for any team you belong to.'));
        }

        if (! Feature::for($team)->active(Api::class)) {
            abort(403, __('API access is not available on your plan.'));
        }

        if (! $team->canConsume('api_calls')) {
            abort(429, __('Monthly API quota exceeded.'));
        }

        app(CurrentTeam::class)->set($team);
        setPermissionsTeamId($team->id);

        $team->recordUsage('api_calls');

        return $next($request);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function resolveTeam(array $abilities): ?Team
    {
        foreach ($abilities as $ability) {
            if (str_starts_with($ability, 'team:')) {
                $id = (int) substr($ability, 5);

                return $id > 0 ? Team::find($id) : null;
            }
        }

        return null;
    }
}
