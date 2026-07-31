<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTeam
{
    public function handle(Team $team): void
    {
        if ($team->personal_team) {
            throw ValidationException::withMessages([
                'team' => __('A personal team cannot be deleted.'),
            ]);
        }

        if ($team->hasActiveSubscription()) {
            throw ValidationException::withMessages([
                'team' => __('Cancel the subscription before deleting the team.'),
            ]);
        }

        DB::transaction(function () use ($team): void {
            foreach ($team->members as $member) {
                $member->removeTeamRoles($team);
            }

            // Members pointing at this team are healed to another team
            // by the SetCurrentTeam middleware on their next request.
            User::where('current_team_id', $team->id)
                ->update(['current_team_id' => null]);

            $team->delete();
        });

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['team' => $team->name, 'team_id' => $team->id])
            ->log('team.deleted');
    }
}
