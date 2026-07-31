<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;

/**
 * Staff-only, time-boxed user impersonation. Every start, stop, and
 * expiry is written to the activity log.
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonation';

    public const TTL_MINUTES = 60;

    public function start(User $staff, User $target): void
    {
        if (! $staff->is_staff) {
            throw new InvalidArgumentException('Only staff may impersonate users.');
        }

        if ($target->is_staff || $target->is($staff)) {
            throw new InvalidArgumentException('This user cannot be impersonated.');
        }

        activity()
            ->causedBy($staff)
            ->performedOn($target)
            ->withProperties(['ttl_minutes' => self::TTL_MINUTES])
            ->log('impersonation.started');

        Session::put(self::SESSION_KEY, [
            'impersonator_id' => $staff->id,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ]);

        Auth::login($target);
    }

    public function stop(string $reason = 'impersonation.stopped'): void
    {
        $impersonator = $this->impersonator();

        if ($impersonator === null) {
            return;
        }

        $impersonated = Auth::user();

        if ($impersonated !== null) {
            activity()
                ->causedBy($impersonator)
                ->performedOn($impersonated)
                ->log($reason);
        }

        Session::forget(self::SESSION_KEY);

        Auth::login($impersonator);
    }

    public function active(): bool
    {
        return Session::has(self::SESSION_KEY.'.impersonator_id');
    }

    public function expired(): bool
    {
        $expiresAt = Session::get(self::SESSION_KEY.'.expires_at');

        return is_int($expiresAt) && $expiresAt < now()->getTimestamp();
    }

    public function impersonator(): ?User
    {
        $id = Session::get(self::SESSION_KEY.'.impersonator_id');

        return is_int($id) ? User::find($id) : null;
    }
}
