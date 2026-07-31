<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentTeamController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
        ]);

        $team = Team::findOrFail((int) $validated['team_id']);

        if (! $request->user()->switchTeam($team)) {
            abort(403);
        }

        return redirect()->route('dashboard');
    }
}
