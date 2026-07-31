<?php

namespace App\Listeners;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Every new user gets a personal team at registration so the Team is
 * always the billable / tenant entity — even for solo customers.
 */
class CreatePersonalTeam
{
    public function __construct(protected CreateTeam $createTeam) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->personalTeam() !== null) {
            return;
        }

        $this->createTeam->handle($user, $user->name."'s Team", personal: true);
    }
}
