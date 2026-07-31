<?php

use App\Models\User;

test('the health endpoint reports database and redis status', function () {
    $this->get('/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', true);
});

test('the framework health endpoint is registered', function () {
    $this->get('/up')->assertOk();
});

test('staff can view horizon and non-staff cannot', function () {
    $staff = User::factory()->staff()->create();
    $user = User::factory()->create();

    $this->actingAs($staff)->get('/horizon')->assertOk();
    $this->actingAs($user)->get('/horizon')->assertForbidden();
});

test('staff can view pulse and non-staff cannot', function () {
    $staff = User::factory()->staff()->create();
    $user = User::factory()->create();

    $this->actingAs($staff)->get('/pulse')->assertOk();
    $this->actingAs($user)->get('/pulse')->assertForbidden();
});
