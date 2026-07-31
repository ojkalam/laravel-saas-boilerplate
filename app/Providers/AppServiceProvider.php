<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTeam::class);

        // The Team is the billable entity; the published Cashier
        // migrations are re-targeted at the teams table accordingly.
        Cashier::useCustomerModel(Team::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureFeatures();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    /**
     * Pennant features are scoped to the current Team, and every
     * feature resolves from the team's plan — call sites only ever
     * ask Feature::active('...').
     */
    protected function configureFeatures(): void
    {
        Feature::resolveScopeUsing(fn ($driver) => app(CurrentTeam::class)->model());

        Feature::discover();
    }

    protected function configureDefaults(): void
    {
        // Staff members bypass all authorization checks. Keep staff
        // accounts rare, audited, and never customer-facing.
        Gate::before(fn (User $user): ?bool => $user->is_staff ? true : null);

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
