# Warehouse Management

An integrated warehouse and store application. The warehouse has an admin panel for laying out storage racks and tracking stock, plus a mobile-facing API for warehouse staff. The store under `/temp` includes a storefront, seller area, Inertia back office, and customer, seller, delivery, driver/trip and warehouse integration APIs. Storefront, seller, auth, email and PDF views remain Blade; see the [store integration guide](.ai/rules/legacy-albaraka-holland.md) and [back-office migration guide](.ai/rules/store-backoffice-migration.md).

## Domain model

The warehouse is laid out as a grid:

- **Row** — a physical storage row, identified by a `letter` (e.g. `A`). Has a `cells_count` × `flats_count` grid size.
- **Cell** — one slot in a row's grid, addressed by `(row, cell_number, flat_number)`. A cell is always `empty`, `full`, or `opened` (`App\Enums\CellState`).
- **Pallet** — sits on exactly one cell and holds one `Product`, with an `expiration_date`. A pallet has no state of its own — its state is read through the cell it sits on.
- **Product** — a catalog item a pallet can hold. The integrated app owns its schema; the sync and store write separated columns under Option A; see [Deployment](#deployment).
- **CellStatusLog** — an append-only audit trail. Every time a cell's state changes (stored, opened, emptied, or transferred between cells), a log row is written recording the action, the before/after state, and who did it.

Creating a `Row` automatically generates its full grid of `Cell` rows (see `App\Observers\RowObserver`) — you don't create cells by hand.

## Tech stack

- **Backend**: Laravel 13 (PHP `^8.5`, as required by `composer.json`), Inertia.js v3 for the admin panels, Laravel Sanctum for API authentication, `spatie/laravel-permission` for role-based access, `dedoc/scramble` for auto-generated OpenAPI docs.
- **Frontend**: Vue 3 + TypeScript, Tailwind CSS, `vue-i18n` for translations.
- **Testing**: Pest (backend), Vitest (frontend units), Playwright (e2e).

## Getting started

```bash
composer run setup   # composer install, .env, key:generate, migrate, npm install, npm build
php artisan db:seed  # optional: seed sample rows/products/pallets and a test admin user
composer run dev      # serves the app, queue worker, and Vite dev server together
```

The app is served at the URL in `APP_URL` (`http://localhost:8000` by default).

The seeded user (`test@example.com` / `password`) is an admin by default — `User::factory()` assigns the `admin` role unless the `mobileUser()` state is used. Mobile-app users should carry no roles at all; admin panel access is gated by the `role:admin` middleware, not a policy.

## Warehouse admin panel

An Inertia-driven SPA, session-authenticated, with sign-in at `/admin/login`. Successful login redirects to `/admin` (the dashboard); users required to change their password are routed through `/password/change`. Available areas include:

- `/admin` — warehouse dashboard.
- `/admin/rows` — manage rows (create/edit/delete), view a row's cell grid and what's stored in each cell.
- `/admin/warehouse-map` — browse the warehouse map.
- `/admin/pallets` — browse matching pallets.
- `/admin/products` — configure box counts, minimum stock, barcodes and reference images.
- `/admin/users` — manage warehouse admin and mobile users.
- `/admin/cell-logs` — browse the full cell status audit trail, filterable by product/pallet/row/column/user/action/date range.
- `/admin/cell-verification-rounds` — review verification rounds and reports.
- `/admin/settings` and `/admin/help` — warehouse settings and help.

`/` unconditionally redirects to `https://albaraka-holland.nl/`. The integrated storefront is at `/temp`, store sign-in is at `/temp/login`, and the store back office is at `/temp/admin`. Store accounts use a separate guard from warehouse accounts.

## API (mobile app)

Token-based, under `/api/v1`, authenticated with Sanctum bearer tokens (all routes except login require `auth:sanctum`).

- `POST /api/v1/login` — exchange email/password for a bearer token.
- `POST /api/v1/logout` — revoke the current token.
- `GET /api/v1/rows`, `GET /api/v1/rows/{row}/cells`, `GET /api/v1/rows/{row}/cells/{cellNumber}/flats/{flatNumber}` — browse rows and cells.
- `GET /api/v1/products` — browse the product catalog.
- `POST /api/v1/pallets` — store a new pallet on a cell.
- `GET /api/v1/pallets/{pallet}` — read a pallet and its current contents.
- `POST /api/v1/pallets/{pallet}/open`, `.../remove-boxes`, `.../empty`, `.../transfer` — pallet actions delegated to `PalletActionService`, which locks and updates the affected records and writes audit logs in a transaction. Removing boxes preserves the cell's state; a transfer writes paired source/destination logs.
- `GET /api/v1/cell-logs` — the same audit trail as the admin panel, filtered the same way.

Full request/response schemas are auto-generated from the code — browse them at `/docs/api` once the app is running (see `config/scramble.php`).

## API documentation

Warehouse and Store APIs share one Scramble reference at `/docs/api` and one OpenAPI document at `/docs/api.json`. The document uses the application root as its server URL and preserves full endpoint paths: `/api/v1/*` for warehouse mobile clients and `/temp/api/*` for Store clients. Documentation is available locally; other environments require the `viewApiDocs` gate. Web and back-office pages are excluded.

The reference includes warehouse mobile, customer/catalogue, seller, representative, delivery, driver/trip, and warehouse integration endpoints. Request validation and response schemas are inferred from the implementation, preserving each API's resource envelope. Legacy dynamic payloads may have incomplete inferred schemas.

Each endpoint has one area-and-feature tag. Warehouse groups cover Authentication, Inventory, Pallets, and Audit logs. Store groups cover Authentication, Catalogue, Orders, Customers, Sellers, Delivery & Trips, and Warehouse integration. The Warehouse groups appear first in the shared reference; endpoint paths and authentication requirements are unchanged.

- Send `Accept: application/json`. Sign in with `POST /temp/api/v2/auth/login`, using `login_by`, `email` (also used for phone login), and `password`. Send the returned `access_token` as `Authorization: Bearer <access_token>` on protected routes. Warehouse mobile tokens are separate from store tokens.
- Seller and driver endpoints also enforce their corresponding roles; public catalogue endpoints need no token.
- `/temp/api/v2/warehouse/*` uses `private-token: <configured WMS token>`, documented separately from bearer authentication. The middleware reports an invalid token as a JSON business failure with `status: 401` and HTTP 200.
- Check `result`, `success`, and `status` where present: some legacy validation/business failures use HTTP 200. Disabled payment gateways return 404. Throttled routes can return 429.

For example, sign in to the Store and use the returned token:

```bash
curl --request POST "$APP_URL/temp/api/v2/auth/login" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{"login_by":"email","email":"customer@example.com","password":"your-password"}'

curl "$APP_URL/temp/api/v2/auth/user" \
  --header 'Accept: application/json' \
  --header "Authorization: Bearer $STORE_ACCESS_TOKEN"
```

Export or validate the unified reference with the installed Scramble commands:

```bash
php artisan scramble:export --path=api.json
php artisan scramble:analyze
```

Every registered `/temp/api` route points at an existing controller action, so Scramble documents them all; `OpenApiDocumentationTest` fails if a route is added without one.

## Localization

The app ships English and Arabic translations (`lang/en`, `lang/ar`). Locale is switched via `POST /locale/{locale}` (web) or the `Accept-Language` header (API). Every translation key must exist in both locales — see `.ai/rules/lang.md`.

## Testing & quality

```bash
php artisan test --compact   # Pest (backend)
npm run test:unit            # Vitest (frontend)
npm run test:e2e             # Playwright (e2e)

vendor/bin/pint              # PHP formatting
npm run lint / npm run format
npm run types:check          # vue-tsc
composer run types:check     # phpstan/larastan
composer run ci:check        # everything CI runs
```

## Deployment

The integrated app owns the `products` schema. Under **Option A**, `products:sync` owns `name`, `ar_name` and `published` on feed-origin rows (non-null `synced_at`); the store owns the other columns and may change those three only on store-origin rows. Existing rows default to null/unclassified. Following the owner’s 2026-10-23 confirmation that all current products are from Otajer, a one-time migration classifies the existing catalog as feed-origin; back up products before deployment and run `php artisan products:sync` after migration to refresh statuses. Future store-created rows remain protected from feed ID collisions. Sync skips and logs ID collisions with null-origin rows. [`.ai/rules/shared-database.md`](.ai/rules/shared-database.md) records enforcement, the reclassification procedure and the table-name history.

**Production environment file.** Start the server's `.env` from [`.env.production.example`](.env.production.example), never from `.env.example`: that one is for local development and CI, and its `APP_DEBUG=true`, `SESSION_SECURE_COOKIE=false`, `CSP_REPORT_ONLY=true`, `TELESCOPE_ENABLED=true`, `MAIL_MAILER=log` and `DB_CONNECTION=sqlite` override the safe production defaults. The template holds only placeholders; the go-live checklist ([`.ai/go-live-checklist-20261021.md`](.ai/go-live-checklist-20261021.md), section 2) says what each value means. Create the first admin with `php artisan store:create-admin <email>`; never run `db:seed` in production.

Staging deploys automatically from `develop` (`.github/workflows/deploy-staging.yml`, on a green `tests` run) and runs `php artisan migrate --force` as part of that. So the three items below have to be settled *before* merging, not after.

### 1. Rename the migration bookkeeping table (existing databases only)

`config/database.php` points `migrations.table` at `wms_migrations`, because a shared `migrations` table would leave each app thinking the other's migrations had already run. Laravel reads that setting before it runs anything, so no migration can perform this rename itself.

On any database that has already run this app's migrations — staging, and existing local checkouts — do this once, before the next `migrate`:

```sql
RENAME TABLE migrations TO wms_migrations;
```

Skip it and `migrate` finds an empty repository, tries to replay every migration from the start, and stops on the first one with `table 'wms_users' already exists`. That fails safely — it stops before creating or dropping any application table — but the deploy is stuck until the rename happens. Locally, `php artisan migrate:fresh` is the easier route.

Production needs nothing here: it is a first install against the shared database, so `wms_migrations` is legitimately empty.

Note for later: a database that predates this change keeps its old
`products.id` (`bigint unsigned`) rather than the store's signed `int(11)`. That
is harmless on sqlite, which ignores foreign key types, but MySQL rejects the
`wms_product_settings` foreign key against it. If such a database is ever moved
onto MySQL, rebuild it with `migrate:fresh` rather than migrating it in place.

### 2. Set `STORE_ASSET_BASE_URL`

The store's `uploads.file_name` holds a relative path; the absolute URL is built by whichever app serves the file. `STORE_ASSET_BASE_URL` is the base URL serving those store files. `Product::$image_url` uses a warehouse reference image from the public disk when present, otherwise falls back to the store upload URL.

Leave it unset and relative store upload URLs resolve to `null`. Warehouse reference images and uploads carrying their own `external_link` (a CDN or object store) do not depend on it.

### 3. Configure box counts

`boxes_count` — how many boxes a full pallet of a product holds — is this app's own data, in `wms_product_settings`, because the store's `products` has no column for it. `products.unit_equal` reads like a units-per-carton value but is **not** the box count; it has been checked and ruled out. There is no column in the store's schema to backfill from.

A product with no `wms_product_settings` row falls back to `Product::DEFAULT_BOXES_COUNT` (50), so **every product reads as 50 boxes until configured**, and pallets are placed with a remaining count of 50.

Box counts are set per product from the products screen at `/admin/products` — clicking the count in the **Boxes / pallet** column opens a dialog with an explicit save through `Admin\ProductController::updateBoxCount()`. Creating a minimum-pallet setting also stores the default box count when no settings row exists. The integrated store's product CRUD and the sync follow Option A above.

A freshly deployed catalog therefore needs someone to walk the list and set the real counts; there is nothing to import them from.

## Contributing / conventions

See the [documentation map](.ai/README.md) for warehouse and integrated-store
guidance, the newest required-fields audit and dated historical evidence.

This repo checks in its own convention notes for AI coding agents under [`.ai/rules`](.ai/rules/index.md) (per-area: controllers, models, requests, enums, resources, observers, exceptions, factories, seeders, routes, tests, and the Vue frontend) and broader engineering conventions in [`AGENTS.md`](AGENTS.md). Skim those before making structural changes — they capture settled decisions and non-obvious traps (e.g. why `cell_status_logs.pallet_id` has no FK constraint, or why observers are registered via attribute instead of a service provider) that aren't obvious from a single file.
