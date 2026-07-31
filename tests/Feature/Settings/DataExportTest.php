<?php

use App\Models\Team;
use App\Models\User;

test('a user can download their data export', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $user->forceFill(['current_team_id' => $team->id])->save();

    $response = $this->actingAs($user)->get(route('profile.export'));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="account-export.json"')
        ->assertJsonPath('profile.email', $user->email)
        ->assertJsonPath('teams.0.name', $team->name)
        ->assertJsonPath('teams.0.owner', true);
});

test('guests cannot download an export', function () {
    $this->get(route('profile.export'))->assertRedirect(route('login', absolute: false));
});
