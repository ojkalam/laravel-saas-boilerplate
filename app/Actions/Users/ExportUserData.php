<?php

namespace App\Actions\Users;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * GDPR-style export of a user's personal data as a plain array,
 * ready to be serialized to JSON.
 */
class ExportUserData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'teams' => $user->teams()->get()->map(fn ($team) => [
                'name' => $team->name,
                'role' => $team->memberRole($user),
                'owner' => $team->owner_id === $user->id,
                'personal_team' => $team->personal_team,
            ])->all(),
            'activity' => Activity::causedBy($user)
                ->latest()
                ->limit(1000)
                ->get()
                ->map(fn (Activity $activity) => [
                    'description' => $activity->description,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ])->all(),
        ];
    }
}
