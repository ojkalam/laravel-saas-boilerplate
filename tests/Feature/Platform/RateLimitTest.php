<?php

test('registration is rate limited', function () {
    foreach (range(1, 10) as $i) {
        $this->post('/register', []);
    }

    $this->post('/register', [
        'name' => 'Over Limit',
        'email' => 'over@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(429);
});

test('password reset requests are rate limited', function () {
    foreach (range(1, 10) as $i) {
        $this->post('/forgot-password', ['email' => 'someone@example.com']);
    }

    $this->post('/forgot-password', ['email' => 'someone@example.com'])
        ->assertStatus(429);
});

test('login attempts are rate limited by fortify', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $this->post('/login', [
        'email' => 'nobody@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
