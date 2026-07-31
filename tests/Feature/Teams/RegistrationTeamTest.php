<?php

use App\Models\User;

test('registration creates a personal team and sets it current', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $team = $user->personalTeam();

    expect($team)->not->toBeNull()
        ->and($team->personal_team)->toBeTrue()
        ->and($team->owner_id)->toBe($user->id)
        ->and($user->current_team_id)->toBe($team->id)
        ->and($user->teamRole($team))->toBe('owner');
});
