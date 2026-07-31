<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property int|null $current_team_id
 * @property bool $is_staff
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_staff' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * All teams the user belongs to (including teams they own).
     *
     * @return BelongsToMany<Team, covariant $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Team, covariant $this>
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Team, covariant $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function personalTeam(): ?Team
    {
        return $this->ownedTeams()->where('personal_team', true)->first();
    }

    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->whereKey($team->id)->exists();
    }

    public function ownsTeam(Team $team): bool
    {
        return $this->id === $team->owner_id;
    }

    public function teamRole(Team $team): ?string
    {
        return $team->memberRole($this);
    }

    /**
     * Check a Spatie permission against an explicit team, regardless of
     * the currently bound team context. Always prefer this in policies —
     * it cannot be fooled by a stale global team id.
     */
    public function hasTeamPermission(Team $team, string $permission): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $previous = getPermissionsTeamId();

        setPermissionsTeamId($team->id);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $this->hasPermissionTo($permission);
        } finally {
            setPermissionsTeamId($previous);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * Assign a role to the user scoped to the given team.
     */
    public function assignTeamRole(Team $team, string $role): void
    {
        $previous = getPermissionsTeamId();

        setPermissionsTeamId($team->id);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            $this->assignRole($role);
        } finally {
            setPermissionsTeamId($previous);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * Remove all of the user's roles scoped to the given team.
     */
    public function removeTeamRoles(Team $team): void
    {
        $previous = getPermissionsTeamId();

        setPermissionsTeamId($team->id);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            $this->syncRoles([]);
        } finally {
            setPermissionsTeamId($previous);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * Switch the user's current team. Refuses teams the user is not a member of.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->forceFill(['current_team_id' => $team->id])->save();
        $this->setRelation('currentTeam', $team);

        return true;
    }
}
