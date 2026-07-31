# Marketplace — Architecture & Build Plan

Digital marketplace built on the existing SaaS boilerplate: **buyers (teams) purchase
themes and apps** for their business. Public storefront → Stripe Checkout → licensed,
versioned downloads in a buyer portal. Staff curate the catalog in the Filament
back-office.

**Model researched against:** Easy Digital Downloads + Software Licensing (license
keys, activations, update delivery), Envato-style curated catalogs, AWS guidance on
short-lived signed download URLs.

---

## 1. Architecture decisions

| Decision | Choice | Why |
|---|---|---|
| Marketplace type | **First-party / curated** (admin publishes products) | Matches the ask ("admin can manage those things"); avoids multi-vendor payouts, KYC, commission splits |
| Buyer entity | **Team** (not User) | Reuses the boilerplate's tenancy + billing identity; licenses/orders are team assets |
| Payments | **Stripe Checkout one-time payments** via Cashier (`checkout` with ad-hoc `price_data`) | No Stripe product sync needed; fulfillment is webhook-driven and idempotent |
| Fulfillment | `checkout.session.completed` webhook → mark order paid → issue licenses → email receipt | Stripe retries webhooks; handler is idempotent by order id |
| Licensing | EDD-style: **license key per purchased product**, activation limit, 1-year updates window | Standard for themes/apps; supports a future "phone-home" API |
| File delivery | Private disk + **15-min signed URLs** through an authorizing controller, download audit + daily limit | Research consensus: short expiry, authorize before signing, log every download |
| Versioning | `product_versions` (semver + changelog + file); buyers download any version while license is within its updates window | Update delivery is the point of licensing |
| Storefront | Public Livewire pages (`/marketplace`) | Same stack as the rest of the app |
| Admin | Filament resources (products, versions, orders, licenses) + revenue widget | Back-office already exists and is staff-gated |

## 2. Data model

```
product_categories   id, name, slug
products             id, category_id, type(theme|app), name, slug, summary,
                     description, price (cents), status(draft|published|archived),
                     featured, downloads_count
product_versions     id, product_id, version, changelog, file_path, file_size, released_at
product_images       id, product_id, path, position
orders               id, team_id, user_id, number, status(pending|paid|refunded),
                     currency, total, stripe_checkout_session_id, paid_at   [team-scoped]
order_items          id, order_id, product_id, product_name, unit_price
licenses             id, team_id, order_item_id, product_id, key, status(active|revoked),
                     activation_limit, expires_at (updates window)          [team-scoped]
license_activations  id, license_id, instance (domain/host), activated_at
downloads            id, team_id, license_id, product_version_id, user_id, ip [team-scoped]
```

Tenant-owned tables (`orders`, `licenses`, `downloads`) use the existing
`BelongsToTeam` trait → automatically covered by the cross-tenant leakage tests.
Catalog tables are global (public data).

## 3. Flows

**Purchase:** storefront → Buy (auth + current team) → local `pending` order →
`$team->checkout()` with `price_data` + `metadata.order_id` → Stripe Checkout →
webhook marks paid, generates license keys, increments product download counters,
emails receipt with keys. Free products skip Stripe and fulfill immediately.
Refund (admin or Stripe dashboard) → `charge.refunded` webhook → order refunded +
licenses revoked.

**Download:** portal → "Download" → controller re-checks license (active, version
within updates window) → logs to `downloads` → enforces per-license daily limit →
streams from the private disk. Links are 15-minute signed URLs.

**License API (apps phoning home):** `POST /api/v1/license/activate|deactivate`,
`GET /api/v1/license/check`, `GET /api/v1/license/latest-version` — keyed by the
license key, throttled, no session auth (server-to-server).

## 4. Build order (each phase: verify Pint + Larastan + Pest, then push)

- **M1 Catalog** — migrations, models, factories, policies; Filament: categories,
  products (+versions & images relation managers, private file uploads, publish flow)
- **M2 Storefront** — `/marketplace` browse (filter type/category, search, sort),
  product detail (screenshots, changelog, buy button); SEO-friendly public pages
- **M3 Checkout & fulfillment** — orders + items, Cashier checkout, webhook
  fulfillment (idempotent), license generation, receipt mail, free-product flow,
  refund handling
- **M4 Buyer portal** — purchases list, license keys, activations management,
  version downloads via signed URLs with audit + limits
- **M5 License API + admin ops** — activate/check/deactivate/latest-version
  endpoints; Filament: order refund action, license revoke/extend; revenue dashboard
  widget
- **M6 Polish** — demo seeder, README, full-suite verification sweep

## 5. Explicitly out of scope (v1)

Multi-vendor sellers & payouts, product reviews/ratings, cart with multiple items
(one product per checkout keeps fulfillment simple), coupons, affiliate tracking,
theme live-preview hosting.
