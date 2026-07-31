<?php

namespace App\Models;

use App\Support\Plans\Plan;
use App\Support\Plans\PlanRegistry;
use Carbon\CarbonImmutable;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $slug
 * @property bool $personal_team
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property CarbonImmutable|Carbon|null $trial_ends_at
 * @property CarbonImmutable|Carbon|null $created_at
 * @property CarbonImmutable|Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'personal_team'])]
class Team extends Model
{
    use Billable;

    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * The team's effective plan: the subscribed plan when there is an
     * active subscription, the trial plan during the no-card trial,
     * otherwise the default (free) plan.
     */
    public function plan(): Plan
    {
        $registry = app(PlanRegistry::class);

        $subscription = $this->subscription('default');

        if ($subscription !== null && $subscription->valid()) {
            $price = $subscription->stripe_price;

            if ($price !== null && ($plan = $registry->fromStripePrice($price)) !== null) {
                return $plan;
            }
        }

        if ($this->onGenericTrial()) {
            return $registry->trialPlan();
        }

        return $registry->default();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscribed('default');
    }

    /**
     * True once a past_due subscription has exhausted the grace period.
     * Callers should degrade the team to read-only, not hard-lock it.
     */
    public function isReadOnly(): bool
    {
        $subscription = $this->subscription('default');

        if ($subscription === null || $subscription->stripe_status !== 'past_due') {
            return false;
        }

        return $subscription->updated_at
            ->addDays((int) config('plans.grace_period_days'))
            ->isPast();
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * All members of the team, including the owner.
     *
     * @return BelongsToMany<User, covariant $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<TeamInvitation, covariant $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * @return HasMany<Project, covariant $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function memberRole(User $user): ?string
    {
        $role = DB::table('team_user')
            ->where('team_id', $this->id)
            ->where('user_id', $user->id)
            ->value('role');

        return is_string($role) ? $role : null;
    }

    public static function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
