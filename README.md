# Laravel SaaS Boilerplate

A production-grade SaaS starter built on **Laravel 13**, **Livewire 4 + Flux UI**, **PostgreSQL**, and **Stripe** — with single-database, team-scoped multi-tenancy, plan-driven entitlements, and a Filament v5 staff back-office.

Built following the plan in [`laravelsaasboilerplate.md`](laravelsaasboilerplate.md).

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13, PHP 8.4+ |
| UI | Livewire 4 (single-file components) + Flux UI + Tailwind 4 |
| Auth | Fortify (login, registration, reset, email verification, 2FA, passkeys) |
| Database | PostgreSQL |
| Cache / queues | Redis (predis) + Horizon |
| Billing | Cashier Stripe 16 — the **Team** is the billable entity |
| Roles | spatie/laravel-permission 8 (teams mode) |
| Entitlements | Laravel Pennant (team-scoped, plan-driven) |
| Back-office | Filament v5 at `/admin` (staff only) |
| API | Sanctum team-scoped tokens under `/api/v1` |
| Observability | Pulse, Sentry (DSN-gated), activity log, spatie/laravel-backup |
| Quality | Pest 5, Larastan level 7, Pint, GitHub Actions CI |

## Getting started

```bash
git clone <repo> && cd laravel-saas-boilerplate
composer install && npm install
cp .env.example .env && php artisan key:generate

createdb laravel_saas_boilerplate          # PostgreSQL
php artisan migrate --seed                 # seeds roles/permissions
npm run build
composer run dev                           # server + queue + vite
```

Tests run against a dedicated Postgres database:

```bash
createdb laravel_saas_boilerplate_test
composer test        # pint + phpstan + pest
php artisan test --parallel
```

## How the tenancy works

- Every tenant-owned table carries a `team_id`. The [`BelongsToTeam`](app/Models/Concerns/BelongsToTeam.php) trait adds a global scope filtered by the current team and stamps `team_id` on create.
- The current team is resolved **once per request** from `auth()->user()->current_team_id` by [`SetCurrentTeam`](app/Http/Middleware/SetCurrentTeam.php) into the [`CurrentTeam`](app/Support/CurrentTeam.php) scoped singleton — never from request input.
- Queued jobs extend [`TenantAwareJob`](app/Jobs/TenantAwareJob.php), which re-binds the team context around `execute()`.
- [`tests/Feature/Tenancy`](tests/Feature/Tenancy) auto-discovers every model using the trait and asserts team B can never read or write team A's rows. New tenant models are covered automatically — give them a factory.
- CI greps for `withoutGlobalScope(s)` outside `app/Filament` and fails the build if found.

Every new user gets a **personal team** at registration; the team (not the user) owns the subscription, so solo → team growth never requires a data migration.

## Billing & entitlements

- [`config/plans.php`](config/plans.php) is the single source of truth: Stripe price ids, per-seat flags, limits, and features per plan. Adding a plan = editing this file.
- `Team::plan()` resolves the active plan from the subscription's Stripe price, the trial plan during the 14-day no-card trial, or free.
- Pennant features (`App\Features\*`) resolve from the plan; call sites only ask `Feature::active(...)`. Subscription webhooks purge cached flags.
- Metered usage: `$team->recordUsage('api_calls')` / `$team->canConsume('api_calls')` backed by an atomic upsert into `usage_counters`, reset on **subscription-anchored** billing periods.
- Stripe Checkout handles the first subscription; the Stripe billing portal handles everything else. Dunning: `past_due` teams keep access through a grace period, then degrade to read-only (never a hard lock).

Set `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` and the `STRIPE_PRICE_*` ids in `.env`, then test webhooks with `stripe listen --forward-to localhost:8000/stripe/webhook`.

## Roles & authorization

Four global roles — `owner`, `admin`, `member`, `billing` — assigned **per team** (spatie teams mode). The [`TeamRole`](app/Enums/TeamRole.php) enum maps roles to permissions; policies check `hasTeamPermission()` against the record's owning team, so a stale team context can never leak authorization. Staff (`users.is_staff`) bypass gates via `Gate::before` and are the only users who can access `/admin`, Horizon, and Pulse.

## API

Tokens are created in **Settings → API tokens** and carry a single `team:{id}` ability. [`SetTeamFromApiToken`](app/Http/Middleware/SetTeamFromApiToken.php) verifies membership, enforces the plan's `api` feature and monthly quota, meters usage, and binds the team before the per-plan rate limiter (`api_rate_per_minute`) runs. Example resource: `/api/v1/projects`.

## Staff back-office

Filament v5 at `/admin`: teams with plan + subscription status, Stripe deep links, credit tool, and **time-boxed, audit-logged user impersonation** (60 min, auto-expiring, staff-only, banner + stop button in the app shell).

## Scheduled jobs

`horizon:snapshot`, nightly `backup:run` / `backup:clean` / `backup:monitor`, `activitylog:clean`, and weekly `pennant:purge` — see [`routes/console.php`](routes/console.php). Run `php artisan schedule:work` locally.

## Health & monitoring

- `/up` — framework health (used by load balancers)
- `/health` — JSON with database + redis checks (503 when degraded)
- `/horizon`, `/pulse` — staff-only dashboards
- Sentry activates when `SENTRY_LARAVEL_DSN` is set

## CI

`.github/workflows/ci.yml` runs on Postgres 17 + Redis: Pint, Larastan (level 7), the tenancy scope-bypass grep, `composer audit`, and the parallel Pest suite (150 tests).
