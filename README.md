# Laravel SaaS Boilerplate

A production-grade SaaS starter built on **Laravel 13**, **Livewire 4 + Flux UI**, **PostgreSQL**, and **Stripe** — with single-database, team-scoped multi-tenancy, plan-driven entitlements, a Filament v5 staff back-office, and a **digital marketplace** for selling themes and apps.

Built following the plans in [`laravelsaasboilerplate.md`](laravelsaasboilerplate.md) and [`MARKETPLACE_PLAN.md`](MARKETPLACE_PLAN.md).

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
- Leaving the tenant scope goes through one named scope, `Model::acrossTeams()` — used only where there genuinely is no current team (Stripe webhooks, queued jobs, key-authenticated API calls, the staff back-office). CI fails the build on any raw `withoutGlobalScope` call outside the trait that defines it, so every exception stays visible in one place.

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

## Marketplace

Sell themes and apps to buyer teams. Catalog data is public; **what a team bought** (orders, licenses, downloads) is team-scoped like everything else.

**Storefront** — `/marketplace` browse with live search, type/category filters and five sort modes; `/marketplace/{slug}` detail with screenshot gallery, release history and a buy panel. Drafts and archived listings 404 publicly.

**Buying** — one product per checkout. A local `pending` order is created, then Stripe Checkout runs with the order id in metadata. **Only the `checkout.session.completed` webhook grants anything** — the browser redirect is treated as untrusted — and fulfillment is idempotent, so Stripe's retries can't mint duplicate licenses. Free products skip Stripe entirely. `charge.refunded` refunds the order and revokes its licenses.

**Licensing** — one key per purchased product, with an activation limit and a 12-month updates window (`config/marketplace.php`). The key never stops working; releases published *after* the window closes stop being downloadable until it's extended.

**Downloads** — two steps: authorize and mint a 15-minute signed URL, then redeem it. Both ends re-run the full check, since a signature only proves the link was issued. Every redemption writes a `downloads` audit row and there's a per-license daily cap so a leaked key can't mirror the files. Release archives live on a private disk and are never served directly.

**License API** for installed copies (`/api/v1/license/*`, key-authenticated, throttled): `activate`, `deactivate`, `check`, `latest-version`, `download`. Instance ids are normalized so `https://Example.com/` and `example.com` are one seat, and re-activation after a redeploy is a no-op rather than an error.

```bash
php artisan db:seed --class=MarketplaceSeeder   # demo catalog (local)
```

## Staff back-office

Filament v5 at `/admin`, grouped into **Marketplace** (products with inline release + screenshot uploads and a publish guard that refuses to publish a product with nothing to download; categories; read-only orders with a refund action; licenses with revoke/restore, extend-updates and change-seats) and **Customers** (teams with plan + subscription status, Stripe deep links, credit tool; users with **time-boxed, audit-logged impersonation** — 60 min, auto-expiring, staff-only, banner + stop button in the app shell). A revenue widget shows month/all-time paid revenue, refunds, active licenses and downloads.

## Scheduled jobs

`horizon:snapshot`, nightly `backup:run` / `backup:clean` / `backup:monitor`, `activitylog:clean`, and weekly `pennant:purge` — see [`routes/console.php`](routes/console.php). Run `php artisan schedule:work` locally.

## Health & monitoring

- `/up` — framework health (used by load balancers)
- `/health` — JSON with database + redis checks (503 when degraded)
- `/horizon`, `/pulse` — staff-only dashboards
- Sentry activates when `SENTRY_LARAVEL_DSN` is set

## CI

`.github/workflows/ci.yml` runs on Postgres 17 + Redis: Pint, Larastan (level 7), the tenancy scope-bypass grep, `composer audit`, and the parallel Pest suite (252 tests).
