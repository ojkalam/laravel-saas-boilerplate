# Laravel SaaS Boilerplate — Stack & Build Plan

**Target architecture:** single database, team-scoped multi-tenancy · Livewire 4 + Flux UI + Tailwind 4 · Stripe via Cashier · Filament v5 back-office
**Verified against:** July 2026 releases (Laravel 13.21, Livewire 4, Filament v5, Cashier Stripe 16.6, spatie/laravel-permission 8.0)

---

## 1. The stack

### Core

| Layer | Choice | Version | Why |
|---|---|---|---|
| Runtime | PHP | **8.4** | Laravel 13's nominal minimum is 8.3, but 13.3+ pulls Symfony 8 components that need 8.4 — so 8.4 is the real floor. Also gives you property hooks |
| Framework | Laravel | **13.x** | Current stable (13.21, July 2026; 13.0 shipped March 2026). Bug fixes to Q3 2027, security to March 2028 |
| Starter kit | `laravel new` → **Livewire kit** | — | Ships Livewire 4, Flux UI, Tailwind 4, and Fortify auth (login, register, reset, email verification, 2FA) already wired |
| UI | Livewire 4 + Flux UI + Alpine | 4.x | Single-file components and islands land in v4 — much less ceremony than v3 |
| CSS | Tailwind CSS | 4.x | Included in the starter kit; CSS-first config, no `tailwind.config.js` |
| Build | Vite | 7.x | Default in the kit |

> Don't scaffold auth by hand. `laravel new` with the Livewire kit gives you ~2 weeks of work for free, and it's maintained by the core team. Jetstream still ships (v5.x) but has been superseded by the starter kits for new projects — start with the kit.

### SaaS-specific packages

| Concern | Package | Notes |
|---|---|---|
| Billing | `laravel/cashier` | Cashier Stripe **16.x** (16.5 added Laravel 13 support; 15.8 is a backport for older apps). Subscriptions, trials, per-seat quantities, webhooks, invoices, Stripe billing portal |
| Roles & permissions | `spatie/laravel-permission` | **8.0** (May 2026), requires PHP 8.3+, Laravel 12/13. Turn on its **teams feature** so a user can be Owner in one team and Viewer in another |
| Entitlements / plan limits | `laravel/pennant` | Feature flags scoped to a **Team**, not a User. This is how plans map to features — see §5 |
| Admin back-office | `filamentphp/filament` | **v5** (Jan 2026, Livewire 4 support). Use it for *your* internal staff panel, not the customer-facing app |
| API tokens | `laravel/sanctum` | Token auth for a public API + future mobile client |
| Audit trail | `spatie/laravel-activitylog` | Compliance asks for this eventually. Cheap to add now, painful to backfill |
| Queues | `laravel/horizon` + Redis | Webhooks, emails, exports, report generation |
| Realtime | `laravel/reverb` | Only if you need live notifications/presence. Skip in v1 otherwise |
| App monitoring | `laravel/pulse` + Sentry | Pulse for slow queries/jobs, Sentry for exceptions |
| Search | `laravel/scout` + Typesense | Add when a customer complains about search, not before |
| Files | `spatie/laravel-medialibrary` | S3-backed uploads with conversions |
| Backups | `spatie/laravel-backup` | DB + storage to S3 on a schedule |
| Settings | `spatie/laravel-settings` | Typed per-team and global settings without a config mess |

### Dev / quality

| Tool | Purpose |
|---|---|
| **Pest 4** | Tests. Add `pestphp/pest-plugin-browser` + `npx playwright install` for real-browser tests — replaces a separate Dusk setup |
| **Larastan** (level 6+) | Static analysis; ratchet the level up over time |
| **Laravel Pint** | Formatting, zero config |
| **Rector** | Automated upgrades between Laravel majors |
| **Laravel Telescope** | Local debugging only — never enable in production |
| **Laravel Sail** or Herd | Local environment. Herd is faster on macOS; Sail if your team is on mixed OSes |

### Infrastructure

- **Database:** PostgreSQL 17. Better JSON, partial indexes, and row-level security if you ever need a harder isolation story.
- **Cache/queue:** Redis (or Valkey).
- **Object storage:** S3 or Cloudflare R2.
- **Hosting:** Laravel Cloud (zero-ops, autoscaling, managed Postgres/Redis) or Forge + Hetzner/DigitalOcean if you want cheaper and don't mind managing servers.
- **Email:** Postmark for transactional, Resend as an alternative.

---

## 2. Tenancy model — how the isolation actually works

You chose **single database + team scoping**. Concretely:

- Every tenant-owned table carries a `team_id` foreign key.
- A `BelongsToTeam` trait adds a **global scope** filtering by the current team, plus a `creating` hook that stamps `team_id` automatically.
- The current team is resolved once per request from `auth()->user()->current_team_id` and stored in a small `CurrentTeam` singleton — never read from a request parameter.

```php
// app/Models/Concerns/BelongsToTeam.php
trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $query) {
            if ($teamId = app(CurrentTeam::class)->id()) {
                $query->where($query->getModel()->qualifyColumn('team_id'), $teamId);
            }
        });

        static::creating(function (Model $model) {
            $model->team_id ??= app(CurrentTeam::class)->id();
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
```

**The three rules that keep this safe:**

1. Queues and console commands have no authenticated user. Every job must accept a `Team` (or `team_id`) and re-bind `CurrentTeam` in `handle()`. Make a `TenantAwareJob` base class and use it without exception.
2. Never `withoutGlobalScopes()` outside of an explicit admin context. Add a Larastan rule or a grep in CI for it.
3. Write one test per tenant-owned model asserting that team B cannot read team A's rows. Automate it: loop over every model using the trait.

**When to reconsider:** if you land an enterprise customer contractually requiring physical data separation, move *that* customer to a dedicated deployment rather than rewriting the whole app onto `stancl/tenancy`. Cheaper, and it doesn't tax the other 99%.

---

## 3. Data model

```
users                 id, name, email, password, current_team_id,
                      two_factor_secret, two_factor_recovery_codes, ...

teams                 id, owner_id, name, slug, personal_team,
                      stripe_id, pm_type, pm_last_four, trial_ends_at

team_user             team_id, user_id, role            (pivot)

team_invitations      id, team_id, email, role, token, expires_at

subscriptions         (Cashier) team_id as the billable key
subscription_items    (Cashier)

features              (Pennant) scope = team

roles / permissions   (Spatie, teams mode enabled)

activity_log          (audit trail)

usage_counters        team_id, metric, period_start, value
```

**Key decision: the `Team` is the billable entity, not the `User`.** Put `Billable` on `Team`. Even solo customers get an auto-created "personal team" at registration. This means you never have to migrate a single-user account into a team later — which is the single most common expensive refactor in Laravel SaaS apps.

---

## 4. Folder structure

Stay close to stock Laravel; deviate only where it earns its keep.

```
app/
├── Actions/                 # single-purpose invokable classes
│   ├── Teams/CreateTeam.php
│   ├── Teams/InviteTeamMember.php
│   └── Billing/SwapPlan.php
├── Models/
│   └── Concerns/BelongsToTeam.php
├── Livewire/                # full-page + nested components
│   ├── Settings/
│   └── Billing/
├── Filament/                # admin panel resources (staff only)
├── Support/
│   ├── CurrentTeam.php
│   └── Plans/PlanRegistry.php
├── Features/                # Pennant class-based features
├── Policies/
└── Jobs/Concerns/TenantAware.php

resources/views/
├── pages/                   # Livewire 4 single-file page components
├── components/
├── flux/                    # customized Flux components
└── layouts/

tests/
├── Feature/Tenancy/         # cross-tenant leakage tests
├── Feature/Billing/         # Stripe webhook + subscription tests
└── Browser/                 # Pest 4 browser tests
```

Use **Actions** rather than fat service classes. One class, one `handle()`, easy to test, easy to queue.

---

## 5. Billing + entitlements design

This is the part most boilerplates get wrong: they check `$team->subscribed('default')` in fifty places, and then adding a plan means touching fifty files.

**Do this instead — a three-layer split:**

**Layer 1 — Plan registry (config, not database).** One PHP config file listing each plan's Stripe price IDs and its entitlements:

```php
// config/plans.php
'pro' => [
    'stripe_monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
    'stripe_yearly'  => env('STRIPE_PRICE_PRO_YEARLY'),
    'limits' => ['seats' => 10, 'projects' => 50, 'api_calls' => 100_000],
    'features' => ['sso' => false, 'audit_log' => true, 'api' => true],
],
```

**Layer 2 — Pennant resolves entitlements from the team's active plan.**

```php
Feature::resolveScopeUsing(fn () => app(CurrentTeam::class)->model());

Feature::define('audit_log', fn (Team $team) => $team->plan()->allows('audit_log'));
```

**Layer 3 — call sites only ever ask a question:**

```php
Feature::active('audit_log');            // boolean feature
$team->canConsume('projects');           // countable limit
```

Adding a plan = editing one config file. Changing a limit = one line.

**Metered limits:** keep a `usage_counters` table with a `(team_id, metric, period_start)` unique index and increment atomically. Reset per billing period using Cashier's period boundaries, not calendar months.

**Stripe wiring checklist:**

- Use **Stripe Checkout** for the first subscription, and the **Stripe billing portal** for plan changes, card updates, and cancellations. Building your own is weeks of work for no differentiation.
- `php artisan cashier:webhook` registers the endpoint; verify signatures and make handlers idempotent (Stripe retries).
- Handle `customer.subscription.updated|deleted`, `invoice.payment_failed`, `invoice.payment_succeeded`.
- Dunning: on `payment_failed`, mark the team `past_due`, email the owner, and degrade to read-only after a grace period — don't hard-lock immediately.
- Test with the Stripe CLI (`stripe listen --forward-to`) and the Stripe test clock for trial/renewal scenarios.

---

## 6. Build order

Ship in this sequence. Each phase is independently deployable.

### Phase 0 — Scaffold (day 1)

```bash
composer global require laravel/installer
laravel new saas-app          # choose: Livewire starter kit, Pest
cd saas-app
npm install && npm run build
composer run dev
```

Then immediately: init git, set up Pint + Larastan + a GitHub Actions CI file that runs `pint --test`, `phpstan`, and `pest`. Do this before writing feature code — retrofitting CI onto a messy repo is miserable.

### Phase 1 — Teams & membership (days 2–5)

- `teams`, `team_user`, `team_invitations` migrations; `current_team_id` on users.
- `CreateTeam` action, called from a `Registered` event listener so every new user gets a personal team.
- Team switcher in the nav; `SetCurrentTeam` middleware binding the `CurrentTeam` singleton.
- `BelongsToTeam` trait + `TenantAware` job base class.
- Invitation flow: signed URL email → accept → attach to `team_user`.

**Gate:** cross-tenant leakage tests pass before anything else is built on top.

### Phase 2 — Roles & policies (days 6–7)

- `spatie/laravel-permission` with teams mode; seed `owner`, `admin`, `member`, `billing` roles.
- Policies for every tenant model. Register `Gate::before` for your staff super-admin.
- `RequiresRole` middleware for route-level checks.

### Phase 3 — Billing (days 8–12)

- `Billable` on `Team`, `cashier:install`, plan config, Checkout + portal, webhook handlers.
- Pricing page, trial handling (14-day, no card), and a `subscribed` middleware.
- Seat-based quantity syncing when members are added/removed.

### Phase 4 — Entitlements (days 13–14)

- Pennant install, plan-driven feature definitions, `usage_counters`, upgrade-prompt Blade component.

### Phase 5 — Admin panel (days 15–17)

```bash
composer require filament/filament:"^5.0"
php artisan filament:install --panels
```

Separate panel at `/admin` behind a staff-only gate: team list, subscription status, **user impersonation** (log every impersonation to the activity log), and manual credit/refund tools.

### Phase 6 — Account & settings (days 18–20)

Profile, password, 2FA (Fortify already provides it), team settings, member management, danger zone (leave team / delete team with a confirmation phrase), and data export for GDPR.

### Phase 7 — Platform hygiene (days 21–25)

Horizon + queue workers, activity log, Pulse, Sentry, `spatie/laravel-backup`, transactional email templates, a `/health` endpoint, and rate limiting on auth + API routes.

### Phase 8 — API (optional)

Sanctum tokens scoped to a team, versioned `/api/v1` routes, per-plan rate limits, and Scramble or Laravel OpenAPI for docs.

---

## 7. Testing strategy

| Layer | Tool | What to cover |
|---|---|---|
| Unit | Pest | Actions, plan registry, usage counters |
| Feature | Pest | Every route × every role; **every tenant model for cross-team leakage** |
| Billing | Pest + Stripe test clock | Trial → paid, upgrade, downgrade, failed payment, cancellation, webhook idempotency |
| Browser | Pest browser plugin + Playwright | Signup → onboarding → checkout, team invite acceptance |
| Static | Larastan level 6 → 8 | Ratchet up per sprint |

Aim for meaningful coverage of the tenancy and billing paths specifically — those are where bugs cost you money or trust. Chasing a global coverage percentage is not a good use of time.

---

## 8. Security checklist

- [ ] Cross-tenant read/write tests for every tenant model, run in CI
- [ ] No `withoutGlobalScopes()` outside `app/Filament` (enforce with a grep step in CI)
- [ ] Policies on every model; `Gate::authorize` in every Livewire action, not just routes
- [ ] Signed + expiring invitation URLs
- [ ] Rate limit login, registration, password reset, and API routes
- [ ] 2FA available for all users, enforceable per team by plan
- [ ] Webhook signature verification; idempotency keys on handlers
- [ ] Impersonation logged, time-boxed, and never available to non-staff
- [ ] `APP_DEBUG=false`, Telescope disabled, and a strict CSP in production
- [ ] Encrypt sensitive columns with Eloquent's `encrypted` cast
- [ ] Automated dependency updates (Dependabot/Renovate) + `composer audit` in CI

---

## 9. Things to deliberately leave out of v1

Every one of these is a common boilerplate trap that costs weeks and serves nobody until you have paying customers:

- Multi-database tenancy
- A custom billing UI instead of the Stripe portal
- Realtime/websockets before a feature actually needs it
- i18n before you have a non-English customer
- A microservice split
- Your own admin panel instead of Filament
- Custom design system components instead of Flux UI

---

## 10. Buy vs. build

If speed matters more than owning every line, commercial Laravel SaaS starters (Spark, Wave, Larafast, and similar) cover phases 1–5 out of the box. The trade-off is that you inherit someone else's abstractions and get maintenance obligations you didn't design. My read: build it yourself if this boilerplate is meant to be reused across several of your own products — the ~4 weeks above pays back — and buy if you're racing to validate a single product idea.

---

## Sources

- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases) and [laravel/framework on Packagist](https://packagist.org/packages/laravel/framework) — 13.0 March 2026, 13.21 current
- [Laravel 13 starter kits documentation](https://laravel.com/docs/13.x/starter-kits)
- [Laravel Pennant documentation](https://laravel.com/docs/13.x/pennant)
- [Laravel Cashier (Stripe) documentation](https://laravel.com/docs/13.x/billing)
- [Filament v5 release](https://laravel-news.com/filament-5) — Livewire 4 support, Jan 2026
- [Everything new in Livewire 4](https://laravel-news.com/everything-new-in-livewire-4)
- [spatie/laravel-permission on Packagist](https://packagist.org/packages/spatie/laravel-permission) — v8.0.0
- [laravel/cashier-stripe releases](https://github.com/laravel/cashier-stripe/releases) — v16.6.0, Laravel 13 support since 16.5
- [Pest browser testing](https://pestphp.com/docs/browser-testing)
- [Tailwind CSS 4 upgrade guide](https://tailwindcss.com/docs/upgrade-guide)
