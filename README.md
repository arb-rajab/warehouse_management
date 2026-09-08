# Warehouse Management

An internal warehouse management system: an admin panel for laying out storage racks and tracking what's stored where, plus a mobile-facing API for warehouse staff to store, move, and empty pallets on the floor.

## Domain model

The warehouse is laid out as a grid:

- **Row** — a physical storage row, identified by a `letter` (e.g. `A`). Has a `cells_count` × `flats_count` grid size.
- **Cell** — one slot in a row's grid, addressed by `(row, cell_number, flat_number)`. A cell is always `empty`, `full`, or `opened` (`App\Enums\CellState`).
- **Pallet** — sits on exactly one cell and holds one `Product`, with an `expiration_date`. A pallet has no state of its own — its state is read through the cell it sits on.
- **Product** — a catalog item a pallet can hold. In production this table belongs to a separate store app and is read-only here; see [Deployment](#deployment).
- **CellStatusLog** — an append-only audit trail. Every time a cell's state changes (stored, opened, emptied, or transferred between cells), a log row is written recording the action, the before/after state, and who did it.

Creating a `Row` automatically generates its full grid of `Cell` rows (see `App\Observers\RowObserver`) — you don't create cells by hand.

## Tech stack

- **Backend**: Laravel 13 (PHP 8.3+), Inertia.js v3 for the admin panel, Laravel Sanctum for the mobile API, `spatie/laravel-permission` for role-based access, `dedoc/scramble` for auto-generated OpenAPI docs.
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

## Admin panel

An Inertia-driven SPA, session-authenticated, at `/login`. Signed-in users land on `/admin/rows`. Available areas:

- `/admin/rows` — manage rows (create/edit/delete), view a row's cell grid and what's stored in each cell.
- `/admin/users` — manage admin users.
- `/admin/cell-logs` — browse the full cell status audit trail, filterable by product/pallet/row/column/user/action/date range.

There is no public landing page — `/` redirects straight to `/login`.

## API (mobile app)

Token-based, under `/api/v1`, authenticated with Sanctum bearer tokens (all routes except login require `auth:sanctum`).

- `POST /api/v1/login` — exchange email/password for a bearer token.
- `POST /api/v1/logout` — revoke the current token.
- `GET /api/v1/rows`, `GET /api/v1/rows/{row}/cells`, `GET /api/v1/rows/{row}/cells/{cellNumber}/flats/{flatNumber}` — browse rows and cells.
- `GET /api/v1/products` — browse the product catalog.
- `POST /api/v1/pallets` — store a new pallet on a cell.
- `POST /api/v1/pallets/{pallet}/open`, `.../empty`, `.../transfer` — the pallet lifecycle actions; each one atomically updates the cell's state and writes a `CellStatusLog` row.
- `GET /api/v1/cell-logs` — the same audit trail as the admin panel, filtered the same way.

Full request/response schemas are auto-generated from the code — browse them at `/docs/api` once the app is running (see `config/scramble.php`).

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

Production runs this app and an existing store/marketplace app against **one shared MySQL database**. The store owns `products` and `uploads`; this app reads them and never writes to them. Every table this app owns carries a `wms_` prefix so the two can't collide. [`.ai/rules/shared-database.md`](.ai/rules/shared-database.md) carries the full table inventory and the reasoning behind each decision.

Staging deploys automatically from `develop` (`.github/workflows/deploy-staging.yml`, on a green `tests` run) and runs `php artisan migrate --force` as part of that. So the three items below have to be settled *before* merging, not after.

### 1. Rename the migration bookkeeping table (existing databases only)

`config/database.php` points `migrations.table` at `wms_migrations`, because a shared `migrations` table would leave each app thinking the other's migrations had already run. Laravel reads that setting before it runs anything, so no migration can perform this rename itself.

On any database that has already run this app's migrations — staging, and existing local checkouts — do this once, before the next `migrate`:

```sql
RENAME TABLE migrations TO wms_migrations;
```

Skip it and `migrate` finds an empty repository, tries to replay every migration from the start, and stops on the first one with `table 'wms_users' already exists`. That fails safely — it stops before creating or dropping any application table — but the deploy is stuck until the rename happens. Locally, `php artisan migrate:fresh` is the easier route.

Production needs nothing here: it is a first install against the shared database, so `wms_migrations` is legitimately empty.

### 2. Set `STORE_ASSET_BASE_URL`

The store's `uploads.file_name` holds a relative path; the absolute URL is built by whichever app serves the file. `STORE_ASSET_BASE_URL` is the store app's public base URL, and `Product::$image_url` resolves through it.

Leave it unset and every product image resolves to `null` — deliberately, since a relative path would 404 against this app's own domain. Uploads that carry their own `external_link` (a CDN or object store) ignore it.

### 3. Configure box counts

`boxes_count` — how many boxes a full pallet of a product holds — is this app's own data, in `wms_product_settings`, because the store's `products` has no column for it. `products.unit_equal` reads like a units-per-carton value but is **not** the box count; it has been checked and ruled out. There is no column in the store's schema to backfill from.

A product with no `wms_product_settings` row falls back to `Product::DEFAULT_BOXES_COUNT` (1), so **every product reads as 1 box until configured**, and pallets are placed with a remaining count of 1.

Nothing in this app writes that table yet — the admin product screen is read-only (`index`/`search`), and product CRUD belongs to the store app. Populating box counts needs a deliberate mechanism (an admin field, an import command, or a seeded set); until one exists, treat the box count as unconfigured rather than as a real value.

## Contributing / conventions

This repo checks in its own convention notes for AI coding agents under [`.ai/rules`](.ai/rules/index.md) (per-area: controllers, models, requests, enums, resources, observers, exceptions, factories, seeders, routes, tests, and the Vue frontend) and broader engineering conventions in [`CLAUDE.md`](CLAUDE.md). Skim those before making structural changes — they capture settled decisions and non-obvious traps (e.g. why `cell_status_logs.pallet_id` has no FK constraint, or why observers are registered via attribute instead of a service provider) that aren't obvious from a single file.
