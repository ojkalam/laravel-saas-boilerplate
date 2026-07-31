<?php

use App\Http\Middleware\SetCurrentTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', SetCurrentTeam::class, 'team.role:owner,admin'])
        ->get('/_test/admin-area', fn () => response()->json(['ok' => true]));
});

function userWithCurrentTeamRole(string $role): User
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => $role]);
    $user->assignTeamRole($team, $role);
    $user->forceFill(['current_team_id' => $team->id])->save();

    return $user;
}

test('the role middleware allows listed roles', function (string $role) {
    $this->actingAs(userWithCurrentTeamRole($role))
        ->get('/_test/admin-area')
        ->assertOk();
})->with(['owner', 'admin']);

test('the role middleware rejects unlisted roles', function (string $role) {
    $this->actingAs(userWithCurrentTeamRole($role))
        ->get('/_test/admin-area')
        ->assertForbidden();
})->with(['member', 'billing']);

test('the role middleware rejects users without a team', function () {
    $this->actingAs(User::factory()->create())
        ->get('/_test/admin-area')
        ->assertForbidden();
});
