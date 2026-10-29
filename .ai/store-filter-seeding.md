# Store filter coverage seeders

The entry point is `Database\Seeders\StoreFilterCoverageSeeder`. It is standalone and is deliberately excluded from `DatabaseSeeder`. It runs account, catalogue, reference, activity and engagement seeders in dependency order inside one transaction.

## Manual execution

Development/staging databases were not migrated or seeded during implementation. Tests use isolated in-memory SQLite databases and a dedicated MySQL 8.4 test container.

Prerequisites: installed dependencies, PHP 8.5, otherwise current application schema, and the intended development/staging database configured. Allowed environments: local, development, dev, staging and testing. All others, including production, are refused. Use the same commands separately in development and staging; do not override production's environment to bypass refusal.

```bash
# This cloud environment only; other hosts use their normal PHP runtime.
source /workspace/cloud-setup/activate.sh
cd /workspace/warehouse_management

# Bash: enter a password of at least 16 characters without echoing it or
# placing it in shell history. All newly created fixture accounts use it.
read -rsp 'Fixture account password: ' STORE_FILTER_SEED_PASSWORD
export STORE_FILTER_SEED_PASSWORD
php artisan db:seed --class=StoreFilterCoverageSeeder --no-interaction
unset STORE_FILTER_SEED_PASSWORD
```

Use the actual checkout path on other hosts. Do not run the default DatabaseSeeder, migrate:fresh, or an existing staging seed chain. The runtime password variable takes precedence over `config/store_filter_seed.php`, including with cached config. The config file also supports normal environment configuration before caching. Reruns preserve fixture passwords; the password variable is required whenever a new fixture account is needed.

## Preservation and reruns

No migration or schema change is needed. The seeder adds one reserved `business_settings` row with type `store_filter_seed_v1`; its versioned JSON value tracks `(table_name, seed_key, record_id)` ownership. Tracking keys and record identities are checked for duplicates. The helper locks an existing migration-history row while bootstrapping the index and locks the tracking row for updates; it does not write migration history. Existing settings remain untouched, and a conflicting or malformed reserved setting causes a refusal. Do not delete/repoint this metadata while retaining fixtures.

Only registry-owned records are updated. Existing users, credentials, roles, products, stocks, orders and original business rows remain intact. Existing catalogue matches are reused. Additional products/users are created only for missing fixture scenarios. New related rows may reference existing records without changing them. Reserved email/key collisions fail instead of adopting an original record, and the complete transaction rolls back on failure. Missing owned rows are recreated, and explicitly time-sensitive dates refresh on reruns. No other seed chain is invoked.

Inspect fixture IDs without exposing credentials:

```sql
SELECT value
FROM business_settings
WHERE type = 'store_filter_seed_v1';
```

Main logins are `store-filter-admin@example.invalid`, `store-filter-customer@example.invalid`, `store-filter-representative@example.invalid`, `store-filter-seller@example.invalid`, `store-filter-driver@example.invalid`, and `store-filter-staff@example.invalid`. Additional accounts cover archive, verification, registration and activity states, pickup staff and trip drivers. Some intentionally incomplete/archived accounts are filtering examples. The new admin gets a dedicated fixture role with current store permissions; the new staff role has upload access. Existing accounts receive no new grants.

## Coverage inventory and matching inputs

Individual discrete options are covered. Arbitrary filter combinations, text, date/price ranges and pagination page numbers cannot all be guaranteed to match.

| Surface / filters | Fixtures and sample matching inputs |
| --- | --- |
| Admin customers and representatives | Both values for important, verified, from_api, registration_completed and archived; new_user/has_been_contacted activity; default important-customer scope retained |
| Admin catalogue | Active/for-sale/featured switches, deleted presets, approval, existing categories/sellers, all six rating/sales/price sorts |
| Storefront catalogue | Existing category/brand/color/attribute gaps; keyword `filter-coverage`; price 20–30; newest/oldest/price ascending/descending |
| Classified catalogue | Category/brand fixtures; new/used condition; all six public sorts |
| Product API | Five search sort keys, price 20–30, category/brand data and barcode `STORE-FILTER-COVERAGE` |
| Product subsets | Missing digital and today/new/offer scenarios |
| Orders | All/in-house/seller/pickup lists, every accepted delivery state and paid/unpaid; cancelled remains unpaid; search code `FILTER-activity`; creation/delivery dates refreshed to run date |
| Representative payments/visits | Pending/accepted/rejected, search `FILTER-RECEIPT`, today's open and ordered visits |
| Representative customer lookup | Name `Filter fixture customer`, company `Filter coverage company`, account-code input `987654321` |
| Trips/trucks | Pending/started/closed, including today's trips, separate drivers/trucks and checkpoints; available/unavailable trucks; search `FILTER-TRIP` |
| Reports | Existing category options, verification 0/1, per-seller commissions, wallet user options; use today as date_from/date_to or created_within_days=1 |
| Support/marketing/finance | Ticket searches `987650`, `987651`, `987652`; campaign/blog search `Filter coverage`; questions/replies/conversations, seller payments/withdrawals, coupons, offers/deals and related products, subscribers, push-log records |
| Reference/setup | Active country/state dropdowns have child rows; reviews have both rating sorts; five upload types with four sorts; missing package/language/page/setup baselines, staff and notification metadata; brand-discount listing reuses catalogue brands |

Source translation search gets a `store_filter_coverage_example` key only if the English source table is empty. Otherwise search an existing source key. Use the complete entry point for prerequisite order.

## Limits and existing behavior

- Existing settings stay unchanged. Conversation/classified/color and other optional feature visibility depends on current settings. A disabled screen cannot be enabled under the preservation constraint. Classified endpoint tests enable the setting only in the isolated database.
- Upload fixtures contain metadata with null URLs, without downloadable media files.
- No email, SMS, push, payment, webhook or external sync operation is dispatched by seeding. Most inserts bypass events; product writes use the guarded model with events disabled. Financial/stock rows represent fixture states rather than real settlement or stock-debit operations.
- Three read-query eager-loading changes were needed to render multi-row fixtures under strict lazy-loading protection: storefront search, API product search and representative-payment API. Their filter predicates and authorization rules are unchanged.
- Existing representative-payment API related-name search has ungrouped OR clauses around lines 31–37 of `app/Http/Controllers/Api/V2/RepresentativePaymentController.php`, which can return another representative's receipts. This pre-existing ownership bug was not changed as part of data seeding. Tests prove the intended receipt-code search and representative fixture path, not security of every search branch.
- Verification uses SQLite and an isolated MySQL 8.4 test database. Development/staging databases and their current data remain untouched.

## Verification

The updated seeding suite passes 47 tests (1,977 assertions); scoped PHPStan reports zero errors, and Pint and diff checks pass. The suite verifies seeding without new tables or migrations, existing-row preservation, rerun identity/count stability, repair, relative-date refresh, credential collisions, full-transaction rollback, environment refusal, and duplicate/corrupt ownership metadata rejection. Tests exercise actual HTTP filters. PHP formatting and scoped static analysis are also checked. PHPStan requires `--debug` here because worker subprocess execution exits without diagnostics.

Continued verification: all 2,730 frontend unit tests pass across 341 files with `--maxWorkers=2`. The required MySQL migration checks plus the filter-seeder tests pass against MySQL 8.4 (930 passed, 6 skipped; 6,474 assertions). The MySQL server runs in a dedicated container matching the CI image; no system database installation or application schema change is required.

The complete Playwright browser suite also passes: 156 tests. Browser checks use built assets (temporarily moving a stale ignored `public/hot` marker aside) and the repository's idempotent Playwright setup helper. Verification may set `VITEST_MAX_WORKERS=2` and use the pre-push runner's `--serial` mode to avoid contention without changing test coverage or timeouts.
