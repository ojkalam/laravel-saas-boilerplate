<?php

use App\Models\User;

test('staff can access the admin panel', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/admin')->assertOk();
});

test('non-staff users are forbidden from the admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('guests are redirected to the panel login', function () {
    $this->get('/admin')->assertRedirect();
});
