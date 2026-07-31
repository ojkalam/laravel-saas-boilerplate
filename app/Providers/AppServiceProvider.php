<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
        $this->configureMonitoringGates();
    }

    /**
     * Throttle the sensitive Fortify POST endpoints that do not have
     * their own limiter (login and 2FA are already limited by Fortify).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('auth-sensitive', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        $this->app->booted(function (): void {
            foreach ($this->app->make('router')->getRoutes()->getRoutes() as $route) {
                if (in_array('POST', $route->methods(), true)
                    && in_array($route->uri(), ['register', 'forgot-password', 'reset-password'], true)) {
                    $route->middleware('throttle:auth-sensitive');
                }
            }
        });
    }

    /**
     * The Horizon and Pulse dashboards are staff-only.
     */
    protected function configureMonitoringGates(): void
    {
        Gate::define('viewPulse', fn (User $user): bool => $user->is_staff === true);
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
