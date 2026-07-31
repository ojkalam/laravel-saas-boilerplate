<?php

use App\Models\User;
use App\Support\Impersonation;
use Spatie\Activitylog\Models\Activity;

test('staff can impersonate a customer and it is logged', function () {
    $staff = User::factory()->staff()->create();
    $customer = User::factory()->create();

    $this->actingAs($staff)
        ->post(route('impersonation.store', $customer))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($customer);

    expect(session()->get('impersonation.impersonator_id'))->toBe($staff->id)
        ->and(Activity::where('description', 'impersonation.started')
            ->where('causer_id', $staff->id)
            ->where('subject_id', $customer->id)
            ->exists())->toBeTrue();
});

test('non-staff users cannot impersonate anyone', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->post(route('impersonation.store', $target))
        ->assertForbidden();

    $this->assertAuthenticatedAs($user);
});

test('staff cannot impersonate other staff', function () {
    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->post(route('impersonation.store', $otherStaff))
        ->assertForbidden();
});

test('stopping impersonation reverts to the staff user and is logged', function () {
    $staff = User::factory()->staff()->create();
    $customer = User::factory()->create();

    $this->actingAs($staff)->post(route('impersonation.store', $customer));

    $this->delete(route('impersonation.stop'))->assertRedirect('/admin');

    $this->assertAuthenticatedAs($staff);

    expect(session()->has('impersonation'))->toBeFalse()
        ->and(Activity::where('description', 'impersonation.stopped')->exists())->toBeTrue();
});

test('an expired impersonation session auto-reverts', function () {
    $staff = User::factory()->staff()->create();
    $customer = User::factory()->create();

    $this->actingAs($staff)->post(route('impersonation.store', $customer));
    $this->assertAuthenticatedAs($customer);

    $this->travel(Impersonation::TTL_MINUTES + 5)->minutes();

    $this->get('/dashboard')->assertRedirect('/admin');

    $this->assertAuthenticatedAs($staff);

    expect(Activity::where('description', 'impersonation.expired')->exists())->toBeTrue();
});

test('stopping without an active impersonation is forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('impersonation.stop'))->assertForbidden();
});
