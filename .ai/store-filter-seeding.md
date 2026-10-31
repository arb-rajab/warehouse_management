# Store filter coverage seeders

The entry point is `Database\Seeders\StoreFilterCoverageSeeder`. It is standalone and is deliberately excluded from `DatabaseSeeder`. It runs account, catalogue, reference, activity and engagement seeders in dependency order inside one transaction.

## Manual execution

Development/staging databases were not migrated or seeded during implementation. Tests use isolated in-memory SQLite databases and a dedicated MySQL 8.4 test container.

Prerequisites: installed dependencies, PHP 8.5, otherwise current application schema, and the intended development/staging database configured. Allowed environments: local, development, dev, staging and testing. All others, including production, are refused. Use the same commands separately in development and staging; do not override production's environment to bypass refusal.

```bash
# This cloud environment only; other hosts use their normal PHP runtime.
source /workspace/cloud-setup/activate.sh
cd /workspace/warehouse_management

php artisan db:seed --class=StoreFilterCoverageSeeder --no-interaction
```

Use the actual checkout path on other hosts. Do not run the default DatabaseSeeder, migrate:fresh, or an existing staging seed chain. New fixture accounts use the literal password `password`, stored as a hash. No dedicated environment variable or configuration is required. Reruns preserve existing fixture passwords, including passwords changed after the first run.

## Preservation and reruns

No migration or schema change is needed. The seeder adds one reserved `business_settings` row with type `store_filter_seed_v1`; its versioned JSON value tracks `(table_name, seed_key, record_id)` ownership. Tracking keys and record identities are checked for duplicates. The helper locks an existing migration-history row while bootstrapping the index and locks the tracking row for updates; it does not write migration history. Existing settings remain untouched, and a conflicting or malformed reserved setting causes a refusal. Do not delete/repoint this metadata while retaining fixtures.

Only registry-owned records are updated. Non-fixture users, credentials, roles, products, stocks, orders and original business rows remain intact. Owned fixtures are refreshed while their identities and passwords are preserved. Existing catalogue matches are reused. Additional products/users are created only for missing fixture scenarios. New related rows may reference existing records without changing them. Reserved email/key collisions fail instead of adopting an original record, and the complete transaction rolls back on failure. Missing owned rows are recreated, and explicitly time-sensitive dates refresh on reruns. No other seed chain is invoked.

Inspect fixture IDs without exposing credentials:

```sql
SELECT value
FROM business_settings
WHERE type = 'store_filter_seed_v1';
```

Main logins are `store-filter-admin@example.invalid`, `store-filter-customer@example.invalid`, `store-filter-representative@example.invalid`, `store-filter-seller@example.invalid`, `store-filter-driver@example.invalid`, and `store-filter-staff@example.invalid`. Additional accounts cover archive, verification, registration and activity states, pickup staff and trip drivers. Some intentionally incomplete/archived accounts are filtering examples. The new admin gets a dedicated fixture role with current store permissions; the new staff role has upload access. Existing accounts receive no new grants.

## Coverage inventory and matching inputs

The seeders use realistic fictional customer and merchant names, grocery products, delivery references, and support/campaign content. Internal ownership keys and reserved login emails remain stable. Rerunning upgrades earlier “Filter …” fixtures in place, preserving their IDs and passwords; non-fixture records stay untouched.

Each individual business filter has enough matching records to reach page two at its default page size: at least 21 for the 20-row admin lists, and 121 for the 120-row storefront search. The catalogue reuses existing matching products and adds only the missing quantity; state combinations also retain their default scopes. Changing page size, arbitrary combined filters, unique-ID lookups, or arbitrary search/date/price inputs are outside this guarantee.

Reference lists are an exception: geographic data is never invented or multiplied to fill pages. Missing regions/cities are supplied only from the curated Netherlands, Belgium, Germany and selected international region/city pairs in `StoreReferenceFilterSeeder`. Unsupported existing geography is left for the normal reference-data setup; existing reference records are preserved.

| Surface / filters | Fixtures and sample matching inputs |
| --- | --- |
| Admin customers and representatives | Both values for important, verified, from_api, registration_completed and archived; new_user/has_been_contacted activity; default important-customer scope retained |
| Admin catalogue | Active/for-sale/featured switches, deleted presets, approval, existing categories/sellers, all six rating/sales/price sorts |
| Storefront catalogue | Existing category/brand/color/attribute gaps; keyword `pantry`; price 20–30; newest/oldest/price ascending/descending |
| Classified catalogue | Category/brand fixtures; new/used condition; all six public sorts |
| Product API | Five search sort keys, price 20–30, category/brand data and barcode `2000000000015` |
| Product subsets | Missing digital and today/new/offer scenarios |
| Orders | All/in-house/seller/pickup lists, every accepted delivery state and paid/unpaid; cancelled remains unpaid; search code `ORD-`; creation/delivery dates refreshed to run date |
| Representative payments/visits | Pending/accepted/rejected, search `RCPT-`, today's open and ordered visits |
| Representative customer lookup | Name `Yasmin Sabbagh`, company `Olive Grove Grocers`, account-code input `987654321` |
| Trips/trucks | Pending/started/closed, including today's trips, separate drivers/trucks and checkpoints; available/unavailable trucks; search `RTM-` |
| Reports | Existing category options, verification 0/1, per-seller commissions, wallet user options; use today as date_from/date_to or created_within_days=1 |
| Support/marketing/finance | Ticket searches `987650`, `987651`, `987652`; offer search `Restock`, deal search `Grocery`, and blog search `Pantry Journal`; questions/replies/conversations, seller payments/withdrawals, coupons, offers/deals and related products, subscribers, push-log records |
| Reference/setup | Supported country/state dropdowns have accurate child rows; reviews have both rating sorts; five upload types with four sorts; missing package/language/page/setup baselines, staff and notification metadata; brand-discount listing reuses catalogue brands |

English source translations are topped up with meaningful delivery, order and customer-account labels when fewer than 21 exist. Search `delivery_estimate` for an exact example. Use the complete entry point for prerequisite order.

## Limits and existing behavior

- Existing settings stay unchanged. Conversation/classified/color and other optional feature visibility depends on current settings. A disabled screen cannot be enabled under the preservation constraint. Classified endpoint tests enable the setting only in the isolated database.
- Upload fixtures contain metadata with null URLs, without downloadable media files.
- No email, SMS, push, payment, webhook or external sync operation is dispatched by seeding. Most inserts bypass events; product writes use the guarded model with events disabled. Financial/stock rows represent fixture states rather than real settlement or stock-debit operations.
- Three read-query eager-loading changes were needed to render multi-row fixtures under strict lazy-loading protection: storefront search, API product search and representative-payment API. Their filter predicates and authorization rules are unchanged.
- Existing representative-payment API related-name search has ungrouped OR clauses around lines 31–37 of `app/Http/Controllers/Api/V2/RepresentativePaymentController.php`, which can return another representative's receipts. This pre-existing ownership bug was not changed as part of data seeding. Tests prove the intended receipt-code search and representative fixture path, not security of every search branch.
- Verification uses SQLite and an isolated MySQL 8.4 test database. Development/staging databases and their current data remain untouched.

## Verification

Focused feature tests exercise the actual filtered endpoints on page two, legacy-fixture upgrades, unchanged non-fixture records, identity/count stability on reruns, missing-record repair, relative-date refresh, password preservation, transaction rollback, environment refusal, and ownership metadata validation. Ownership changes are batched per seeder so the larger dataset does not rewrite its complete JSON registry for every row.

Run the affected seed tests before the complete repository pre-push verification. Full verification uses the existing sequential runner and two Vitest workers on this environment. Browser tests use built assets; temporarily move aside an ignored `public/hot` marker if it points to an unavailable development server. All database verification uses disposable test databases.
