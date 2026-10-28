---
paths:
  - 'resources/views/shop/backend/**'
  - 'resources/js/pages/Store/**'
  - 'resources/js/layouts/StoreAdminLayout.vue'
  - 'resources/js/lib/storeAdminMenu.ts'
  - 'resources/js/lib/storeAccess.ts'
  - 'resources/js/components/StoreAdminNavLink.vue'
  - 'resources/js/components/StoreStaffFormFields.vue'
  - 'resources/js/components/StoreRoleFormFields.vue'
  - 'resources/js/components/StoreRichTextField.vue'
  - 'resources/js/components/StoreBlogFormFields.vue'
  - 'resources/js/components/StoreNewsletterFormFields.vue'
  - 'resources/js/components/StoreNewsletterRecipients.vue'
  - 'resources/js/components/StoreTicketReplyForm.vue'
  - 'resources/js/lib/storeSupportTicket.ts'
  - 'app/Services/Store/**'
  - 'resources/js/components/StoreWebsite*'
  - 'resources/js/components/StoreProduct*'
  - 'resources/js/lib/storeProductForm.ts'
  - 'resources/js/components/StoreGoogleMapFormFields.vue'
  - 'resources/js/components/StoreDashboardChart.vue'
  - 'resources/js/components/StoreSeller*'
  - 'resources/js/components/StoreTripStatusDialog.vue'
  - 'resources/js/components/StoreTripFormFields.vue'
  - 'resources/js/components/StoreTripMap.vue'
  - 'resources/js/components/StoreLeafletMap.vue'
  - 'resources/js/components/StoreVisitMap.vue'
  - 'resources/js/lib/storeTrip.ts'
  - 'app/Http/Controllers/Store/**'
  - 'app/Http/Requests/Store/**'
  - 'app/Http/Resources/Store/**'
  - 'app/Http/Middleware/HandleInertiaRequests.php'
  - 'app/Http/Middleware/HandleStoreBladePages.php'
  - 'app/Http/Middleware/SendSecureHeaders.php'
  - 'routes/shop-admin.php'
  - 'tests/Feature/Store/**'
  - 'tests/e2e/store-*.spec.ts'
  - 'resources/js/components/StoreUpload*'
  - 'resources/js/components/StoreFileManagerDialog*'
  - 'resources/js/components/StoreProfile*'
  - 'app/Services/MultipartUploadService.php'
  - 'resources/js/components/StoreLanguageFormFields.vue'
  - 'resources/js/components/StoreBooleanField.vue'
  - 'app/Http/Middleware/SendNoIndexHeader.php'
  - 'app/Http/Controllers/RobotsController.php'
  - 'app/Http/Controllers/SitemapController.php'
  - 'app/Services/Sitemap.php'
  - 'app/Support/Seo.php'
  - 'app/Support/StructuredData.php'
  - 'resources/views/shop/frontend/partials/seo_meta.blade.php'
  - 'resources/views/shop/frontend/partials/json_ld.blade.php'
  - 'tests/Feature/Storefront*'
  - 'app/Http/Requests/Seller/*Product*Request.php'
  - 'app/Http/Requests/Concerns/ValidatesSellerProductFields.php'
  - 'app/Http/Controllers/Seller/DigitalProductController.php'
  - 'resources/views/shop/seller/product/digitalproducts/**'
  - 'public/assets/js/seller-digital-product-form.js'
---

# Store back-office architecture and operating constraints

Current guidance through 2026-10-26. The Blade-to-Inertia back-office conversion
is complete. This guide describes the maintained architecture, security and
ownership boundaries, operational requirements, and outstanding decisions.
Dated implementation and verification accounts are in the [history index](#history-index).
Historical approvals, branch instructions and checkpoint permissions authorize no
new dependency, business change, production action, commit or push.

Start with [AGENTS.md](../../AGENTS.md), the [rule index](index.md),
[form requirements](form-required-fields.md), [database ownership](shared-database.md)
and the [go-live checklist](../go-live-checklist-20261021.md). The
[legacy integration audit](legacy-albaraka-holland.md) also contains security
constraints; its dated findings describe the inspected source, not a fresh runtime audit.
The [newest required-fields audit](../required-fields-audits.md) has a
[JSON companion](../required-fields-audit-20261026.json); it is evidence from that
date, not proof that every form is correct.

## 1. Decision and scope

The store back office under `/temp/admin` uses Inertia v3, Vue 3 and TypeScript.
The storefront, seller panel, store auth pages, emails and PDFs retain Blade.
Preserve their routes, assets, rendering, guard boundaries and compatibility
handlers. Moving an admin screen never authorizes deleting its public, seller,
API, download or PDF consumers. Feature flags and old checked-in approval claims
are not evidence of present feature-use or deletion approval.

Seller administration and admin classified-product management were converted
rather than deleted. The seller panel and customer-facing classified handlers
remain separate. The seven retained backend Blade templates are protected
consumers, not unfinished admin screens; see [retained templates](#51-actual-retained-backend-blade-files).

## 2. Foundation decisions

### 2.1 Shared props are guard-aware

`HandleInertiaRequests::share()` recognizes store requests from the route's
`UseStoreAuthGuard` middleware, not the default guard, which changes later.
Store props are `name`, `locale`, the narrow `auth.user` shape `{id,name,email}`,
and `store` containing `rtl`, permission names, `isSuperAdmin`, `isRaizerAdmin`
and booleans for `STORE_MENU_SETTINGS`. Everything except `name` is a closure
because `Language` runs later. WMS pages do not receive `store` props.

Super Admin is a flag, matching `Gate::before()`, rather than an expansion into
permission rows. Keep menu settings and shared settings in sync
(`StoreInertiaSharedPropsTest`). The root Blade view uses `store.rtl` for store
pages and the existing Arabic-locale rule for WMS.

### 2.2 StoreAdminLayout and the sidebar

`StoreAdminLayout.vue` and `storeAdminMenu.ts` are the only admin shell/sidebar.
Preserve each entry's any-of permission gate, business-setting gate and
vendor-only gate; `raizer_admin` bypasses setting gates. Empty groups disappear.
Button gates use the same permission names as their action middleware. The
account block uses the store logout route and the current actor's profile and
notifications; cache clearing stays narrowly scoped.

Use Inertia links only for Inertia destinations. `StoreAdminNavLink` uses a
plain anchor for Blade destinations; build URLs through Wayfinder. The shared
layout helpers are `SkipToContentLink`, `isActiveNavUrl`, `LogoutLink.href` and
`ResourceFormPage`'s layout/width support. Avoid merging the otherwise distinct
WMS and store layouts.

### 2.3 Translations

Back-office UI copy lives under `store.*` in `resources/js/locales/{en,ar}.json`.
Merge leaf keys; preserve existing upload namespace shapes. Database-owned
values (custom roles/permissions, category names) are translated server-side
and sent as data. Application-defined permission identifiers and sections use
`StorePermissions` display methods and `lang/{en,ar}/store_permissions.php`;
custom records retain content translation. The translation admin still edits
the `translations` table for dynamic content and remaining email, PDF and mobile
consumers; it does not edit converted back-office, storefront or seller UI copy.
Static customer-facing storefront copy lives in `lang/{en,ar}/storefront.php`;
seller-specific copy uses `seller.php` and remaining server admin messages use
`store_admin.php`. Shared labels reuse existing storefront/validation keys;
see the [staged localization inventory](../localization-migration.md).
Other store locales fall back to English in the
back office. `LanguageSwitcher` uses the `locale` session key shared with the
store's `Language` middleware.

### 2.4 Uploads: selected contract and coexistence

The canonical admin upload resource is exactly `id,name,type,size,url`.
`StoreUploadField` posts comma-separated IDs and accepts `initialFiles`, optional
`id` and `required`, with single/multiple selection. Adapt array/CSV callers to
this contract. Preserve action-specific permissions, per-record ownership,
authorization of **every bulk member before any deletion**, confined paths,
HTTP(S)-only external URLs and storage-failure handling. Cross-owner references
require the canonical library permission and policy; defining a permission
grants no role.

All image upload paths use `ImageOptimizer`: 40-megapixel/20 MiB input limits,
1500px longest edge (1600px warehouse references, 2400px private scans), and a
1 MiB stored raster limit. The base64 API retains its stricter 8 MiB input limit.
Raster uploads are re-encoded even when small to strip unsafe content and metadata.
Admin uploads request WebP, falling back to the source format if the encoder is
unavailable; legacy clients and private scans retain their source format.
The legacy optimization opt-out cannot bypass these limits. Animated images are
refused before GD can flatten them. SVG sanitation is preserved with a 512 KiB
stored limit. This global policy was authorized by the image upload audit task.
Browser compression uses `resources/js/lib/imageUpload.ts` before transport in
`StoreUploadManager`, warehouse reference uploads, the retained Uppy uploader,
and private document forms. Limits match the server. Small images and non-image
documents retain their bytes; unsupported browser codecs fall back to server
validation. The shared Blade partial loads the legacy Vite entry in all three
retained layouts. Native mobile clients are outside this repository; trip photos
arrive through the mobile API and retain server-side enforcement.
Private offers/import storage stays separate. Retain `/temp/aiz-uploader*`, its
Blade responses, assets and endpoints while protected consumers remain.

### 2.5 Content-Security-Policy

WMS and store Inertia pages keep `config/secure-headers.php`'s strict policy.
`HandleStoreBladePages` sets a request-local `SendSecureHeaders::CSP_OVERRIDE`
for Blade responses using `store.blade_csp`; `SendSecureHeaders` merges it.
Never rewrite global config for one request: it leaks into later requests in a
shared process. Preserve the Blade policy and middleware for storefront, seller,
authentication and 503 pages after the admin conversion. CSP is report-only
outside production by default; browser security verification must explicitly
use enforced CSP. Keep secure headers ahead of WAF blocking in middleware order.

Tiptap runs under the strict policy without an exception. Maps bundle Leaflet,
its CSS and marker assets through Vite; the only tile allowance is
`img-src https://tile.openstreetmap.org`. No CDN code, unpkg imports or broader
script/connect/style allowance is needed. Chart.js is bundled and requires no
CSP change. The storefront Google Maps partial remains separate.

### 2.6 Coexistence under /temp

- Vue-to-Blade links use full loads; Blade-to-Inertia links remain ordinary
  anchors and boot Inertia from `app.blade.php`.
- Converted modules retain route names, even when URIs become bound-model routes.
- An Inertia GET reaching Blade receives 409 with `X-Inertia-Location` from
  `HandleStoreBladePages`. This also covers redirects such as logout to login.
- Only the root view `app` denotes an Inertia response. A CMS/policy Blade view
  with a variable named `page` remains Blade.
- Retained uploader `app-url` metadata uses `url('/temp')`, preserving scheme
  and port; file-base metadata uses the existing `my_asset('')` contract.

## 3. Per-module checklist (the staff pattern)

Use this pattern for maintenance of converted modules and any separately
authorized extension. Trace routes and all remaining consumers before deletion.

1. Keep per-action `permission:` names on `Store\Admin` controllers using
   `HasMiddleware`. Define new store permissions through `StorePermissions` in
   the same change; definitions never imply grants.
2. Use typed `Store`/`Update`/`Filter` FormRequests, bound models and explicit
   resources with useful `@property-read` PHPDoc. Shared rules need two real
   callers before extraction. Reference validation returns field errors;
   guard-scoped role references use `Rule::exists(...)->where('guard_name','store')`.
3. Lists select explicit columns, scope eager loads, order deterministically,
   paginate with `FiltersPerPage`/`resolvePerPage($request,20)`, preserve the query
   string and expose the paginated resource plus `filters`.
4. Enforce record invariants (Super Admin rows, own-account rules) directly even
   for a Super Admin: a policy alone can be bypassed by `Gate::before()`.
   Per-record ownership still belongs in the established policy mechanism.
5. Use named-route redirects after writes and `redirectPreservingQuery()` after
   deletes. Keep explicit route methods and route names; state-changing routes
   use POST/PUT/DELETE as appropriate. Regenerate Wayfinder with repository
   tooling; never hand-edit generated actions/routes.
6. Pages live under `Store/Admin`, use `StoreAdminLayout`, `ResourceFormPage`
   and existing tables, field/error/button/pagination/confirmation components.
   A module's shared form group lives in `Store<Resource>FormFields.vue`.
   `ResourceFormPage` has one `after-form` slot outside the Form inside its width
   wrapper. Use Wayfinder actions, bound objects and merged query on deletes.
7. Follow [form-required-fields.md](form-required-fields.md): native constraints,
   conditional marks, required legend and English/Arabic labels agree with
   server rules. Keep the ratchet; new forms do not grow its baseline.
8. Remove an obsolete controller/model from **both** `pint.json` and
   `phpstan.neon` when its last consumer moves. Keep exclusions for retained
   legacy consumers. Trace includes, layouts, uploader fragments and assets
   before deleting a Blade file.
9. Existing Pest tests cover per-action render/payload/validation, missing IDs,
   each unauthorized caller with unchanged database assertions, and real
   pagination with noise data. Reuse `tests/Pest.php` store helpers and move
   migrated cases rather than duplicating legacy cases. Keep permission ratchets
   accurate.
10. Full-mount Vue tests assert actual child props, every static label, permission
    gates, delete confirmation and form requirements. Browser tests cover native
    interaction, RTL, dark contrast and Blade coexistence with enforced CSP,
    isolated test databases, one worker, zero retries and no server reuse.
    Seed browser accounts only under `APP_ENV=testing`.
11. Run checks appropriate to the change under [AGENTS.md](../../AGENTS.md).
    Documentation-only edits require link/anchor/instruction validation and
    `git diff --check`; they do not require application tests. Use the pre-push
    runner only within an authorized code/push workflow and report skipped checks.

## 4. Integrated status (2026-10-26)

All back-office modules use Inertia: staff/roles; reference, shipping, tax and
currency; carriers/languages/settings; reports/notifications; uploads/profile;
customers/packages/representatives/visits; catalogue; website/support;
marketing/blog; logistics; orders/dashboard; and seller administration.
Seller physical/digital catalogue validation and persistence were repaired
separately while their panel remains Blade. Conversion completion does not mean
all inherited defects, scanner findings or production launch gates are closed.

### 4.1 Seller prerequisite and role grants

The launch policy is Super Admin only. Staff roles and production grants require
explicit review. Definition migrations insert store-guard permission names
idempotently, do not alter web-guard rows or existing sections, grant nothing,
and preserve pre-existing grants on rollback. `StorePermissions` is the canonical
`name => section` list; its tests scan controllers/routes/menu for undefined gates.
The seller activation switches retain their restricted `{type,value}` endpoint
and literal `1` response. No general-purpose settings writer is restored.

`StaffPrivileges` limits an editor to held direct/role permissions. An editor
cannot grant an unheld permission, assign a more powerful role, change their own
role, or manage an account with permissions beyond their own. Updating a role
preserves permissions the editor does not hold. Super Admin remains unrestricted;
Super Admin role/account invariants still apply. Who receives staff-edit powers
is an owner decision; removing one's own held permissions can still lock one out.

## 5. Conflict decisions and retained work

- Typed settings writers reject unknown/foreign/reserved keys, including localized
  keys, **before any write**. `business_settings.update` is retired. Timezone is
  config-managed (`app.timezone`, UTC); a settings form accepts only the configured
  value and never writes `.env`. Settings writers forget only `business_settings`.
- Website script fields remain plain code on the restricted compatibility endpoint
  with existing rendering, appearance gate and WAF behavior. New script policy,
  `manage_website_scripts` activation and broad rich-text WAF exceptions stay held.
- `StoreRichTextField` is the shared Tiptap field. Converted content is cleaned on
  write where established; seller/customer HTML is sanitized on output with
  `clean_html`. Preserve the exact storefront print policy and the Blade editor
  assets while consumers remain. Do not infer broader trust or WAF exceptions.
- Leaflet and Chart.js are existing bundled dependencies. Their dated approvals
  document those implementations and do not authorize new packages. Preserve the
  seller dashboard's retained `AIZ.plugins.chart` assets.
- `StoreBooleanField` has one `name`/`value`/`disabled` contract. Product list flags
  share `UpdatesStoreProductFlags`, scoped to the list's products. Keep downloads,
  PDFs, bounded translated product search, shared-session logout and departure ID
  9 behavior. Do not invent missing-data business policy.
- Order entry retains raw tax metadata and line-price-only grand total. Percentage
  conversion and tax/shipping/coupon adjustments need a business decision.
- Root controllers with public/Blade/API consumers stay. `ProductController`
  retains fetch/read/SKU compatibility consumers, but the unconsumed root
  `get_products_by_subcategory` and `search` routes/method are retired (D7).
  The WMS GET `admin.products.search` is separate and unchanged.

### 5.1 Actual retained backend Blade files

Seven protected files remain under `resources/views/shop/backend/`:

- `downloads/{brand,category,user}.blade.php`: PDF bulk-upload references.
- `invoices/invoice.blade.php`: admin/seller invoice PDF.
- `layouts/layout.blade.php`: seller/delivery-boy login and 503.
- `product/products/{sku_combinations,sku_combinations_edit}.blade.php`:
  seller AJAX fragments and compatibility tests.

The old backend app shell and its two navigation includes are retired. Keep
storefront/seller/auth layouts and assets, the Inertia root and Blade CSP.

## 6. Registered migrated controllers and consumers

`routes/shop-admin.php`, controller render calls and `storeAdminMenu.ts` define
current destinations. Preserve their names and each action's response kind
(Inertia, JSON, redirect, file or PDF). The complete migration-time registration
inventory is [historical evidence](../archive/store-backoffice/foundation-and-integration-20261007-20261020.md#6-registered-migrated-controllers-and-consumers);
its route counts and source line numbers are not a current consumer inventory.
Run a fresh scoped route/consumer inventory before changing a handler.

| Feature | Current contract and detailed dated decision record |
| --- | --- |
| Uploads/profile | Canonical picker/resource and current actor only; [foundation decisions](#24-uploads-selected-contract-and-coexistence) |
| Carriers, packages, categories, brands and product lists | Keep per-action gates, tariff/hierarchy/slug/delete/download behavior and public/API consumers; [2026-10-12 contracts](../archive/store-backoffice/changes-20261012.md) |
| Rich text, newsletter/support and website writers | Shared editor, typed allowlists, sanitation, bounded recipient search and script hold; [2026-10-13 contracts](../archive/store-backoffice/changes-20261013.md) |
| Physical/digital admin forms | Translation/default-language behavior, explicit variant rows, upload policy and authority columns; [create contracts](../archive/store-backoffice/changes-20261013.md), [edit contracts](../archive/store-backoffice/changes-20261014.md) |
| Trips and order actions | Existing status/notes/invoice/CMR/download contracts, departure ID 9 and driver-change-only notification; [lists](../archive/store-backoffice/changes-20261014.md), [forms/orders](../archive/store-backoffice/changes-20261015.md), [details](../archive/store-backoffice/changes-20261016.md), [write scoping](#trip-write-scoping) |
| Maps, visits and dashboard | Bundled Leaflet/Chart.js; own-visit-only notes; admin picker does not change storefront Google Maps; [maps](../archive/store-backoffice/changes-20261017.md), [visits/settings/dashboard](../archive/store-backoffice/changes-20261018.md) |
| Seller administration and classified products | Keep finance/impersonation/ownership gates, ordered verification JSON and customer/seller consumers; [2026-10-19 contracts](../archive/store-backoffice/changes-20261019.md), [2026-10-20 contracts](../archive/store-backoffice/changes-20261020.md) |
| Public forms/reference data | Existing server-side bounds, named error bags, fail-closed reCAPTCHA and seeded-reference preservation; [2026-10-21 decisions](../archive/store-backoffice/changes-20261021.md), [registration correction](#registration-business-fields) |
| Seller catalogue | Explicit transactional writers, lock-time ownership, storage bounds, unmatched-stock refusal, exact decimal formatting and strict unknown-key rejection; [physical](../archive/store-backoffice/changes-20261022.md), [digital/import](../archive/store-backoffice/changes-20261023.md) |
| Required-fields back office | Marks/legends follow rules; route-dependent and dynamic-control scanner limitations remain; [Setup/Website](../archive/store-backoffice/changes-20261023.md), [remaining modules](../archive/store-backoffice/changes-20261024.md) |
| Staff privilege limits and payouts | Permission subsets, lock order and conservative full-request accounting; [2026-10-24 decisions](../archive/store-backoffice/changes-20261024.md) |
| Slugless catalogue links and retired product helpers | Read-only URL fallback, preserved payload contracts, scoped route retirement; [2026-10-25 decisions](../archive/store-backoffice/changes-20261025.md) |
| Trip scoping, registration and editor-test readiness | [2026-10-26 decisions and verification limitations](../archive/store-backoffice/changes-20261026.md) |

## Product ownership and seller persistence

[shared-database.md](shared-database.md) is canonical for Option A and its
reclassification procedure. The sync owns exactly `name`, `ar_name`, `published`
on feed-origin rows (non-null `synced_at`); the store owns other columns and may
write those three on store-origin rows. Null pre-marker rows were originally
unclassified; the owner's later Otajer-origin confirmation led to a one-time
classification migration. Do not reapply the earlier pending-classification
snapshot as current guidance. Future store-created rows remain null-origin and
are protected from feed ID collisions. Sync preserves existing store-owned
`updated_at`, does not prune, warns on null-origin collisions and sets the marker
last for MySQL assignment-order compatibility. Preserve locked write-time checks
and atomic refusal of mixed-origin bulk changes. Raw product writes bypassing
these guards are prohibited; do not widen fillable as a shortcut.

Physical/digital seller writers explicitly assign validated columns, set seller
ownership and null origin on create, apply the existing approval setting, and
preserve authority columns on update. Check ownership before validation and again
under lock. Product, stocks, taxes and translations share a transaction; cache
clearing follows commit. Feed-field refusal precedes related-row mutation.
Retain exact decimal strings, column bounds, seller-owned upload references,
variant Cartesian limit 500 and gallery limit 50. Unmatched imported stocks must
be explicitly reconciled; never guess option permutations or discard stock
implicitly. Keep original normal variant-removal behavior. Seller panel repairs
do not establish parity or repaired persistence for every mobile API path.

## Payout accounting

Under the existing transaction, lock the shop then pending withdrawal. Cash/bank
payouts are capped at `max(admin_to_pay,0)` and debit it. Other nonempty methods
are capped at `max(-admin_to_pay,0)` and credit it; they are not cash/bank aliases.
A cash/bank withdrawal pays the **entire request**, within the positive balance.
Refuse partial/excess amounts before any balance, status or payment write using
exact decimal comparisons. Non-cash/bank credits do not close a request. Keep
nullable TRX IDs, max191 and the compatible nullable VARCHAR(191) migration;
paid/nonpending requests remain 404. True partial withdrawals require a separate
accounting/schema/concurrency/idempotency decision and tests. Finance-gate
assignments still need owner sign-off.

## Trip write scoping

Trip order/checkpoint writes require validated positive `trip_id` context.
Resolve children through that parent's checkpoints, restricting orders to the
`Order` morph type. Missing/foreign children are 404; bulk references retain the
422 `id` convention and all-or-nothing rollback. Lock parent before children.
Only `TripStatus::closed` is terminal; closed parents reject child writes with
422 `trip_id`. Reads, reopening/status changes and CMR download remain available.

Mark-delivered accepts `confirmed`, `ready_for_delivery`, `on_delivery` only and
changes only `orders.delivery_status`. It does not change payment, checkpoints,
commissions, notification state or dates. Invoice save/clear still sets
`on_delivery` for any attached order's prior status on an open trip; bulk retains
its existing two targets. Unattached orders have no invoice/delivery controls;
bulk status requires a common trip. Server checks govern stale pages. CMR still
opens the first upload ID only, and printing uses the browser layout.

## Registration business fields

Self-registration stores optional `tax_number` (max100), `company_address`
(max191), and a trade-license upload; the latter uses `TradeLicenseStorage` on
the local **private** disk with a random basename and no upload-table/public copy.
Accept the existing doc/docx/pdf/png/jpg/jpeg content policy, max20000 KiB; reject
strings, external URLs and existing paths in place of uploads. Use multipart
encoding; never repopulate a filename. Name, company and email max191 match real
MySQL columns; phone max20, country max30. Duplicate email fails before writes.
Password/reCAPTCHA/terms/country behavior stays established. The unused shipping
address and country-code inputs were removed; default address remains country/
phone-only. The representative's complete-customer workflow retains its required
fields. Its separate shipping-address max255-versus-varchar191 mismatch remains
unresolved; self-registration no longer asks for it.

## Storefront URLs and SEO

`StorefrontUrl` preserves nonempty slugs verbatim, including `"0"`; null/empty
slugs use named-route `~id-N?catalog_id=N` fallbacks. Resolve by ID only for an
exact matching token/discriminator and a row with a missing slug. The same path
without the discriminator still supports a literal legacy slug. Keep publication,
approval and auction exclusions. Generation performs no query/write or backfill.
Preserve each resource's absolute/relative URL contract and all unrelated payload
fields. Classified/auction legacy ordinary-product destinations are not repaired
by safe serialization; changing those destinations/visibility remains separate.

Canonical origin comes from `APP_URL`, with fixed content-query handling and
matching `og:url`; matching fallback `catalog_id` is identity, not tracking.
Filtered/search listings are `noindex,follow`; protected/auth/error pages are
`noindex,nofollow`. Sitemap deliberately excludes null/empty catalogue slugs.
Keep price visibility restricted to verified accounts when producing structured
data, safe JSON-LD encoding, Blade print sanitation and the explicit policy-page
canonical. `SendNoIndexHeader` runs ahead of auth for admin/seller/API exceptions.
The `/robots.txt` route must not be shadowed by a public file. Owner search-engine,
redirect and launch steps remain in the go-live checklist.

## Operations and outstanding decisions

- Use `.env.production.example` for production and never run demo/account seeders
  there. Some direct demo-data seeders are intentionally unguarded; that is not
  permission to run them. `Clerk` is created only in local/testing.
- Create the first admin with `store:create-admin <email>`. Passwords use secret
  prompts (minimum12, confirmed); no password arguments/env/output. `--promote`
  only assigns Super Admin to an existing eligible, unbanned admin/staff/accountant
  and does not change its password. Review this grant as a privileged action.
- `store:sync-permissions --dry-run` reports missing definitions without writes;
  ordinary sync/migration inserts missing store rows only. Neither assigns roles.
  Existing partially populated permissions require explicit sync before first-admin
  creation; do not infer deployed grants from source definitions.
- On existing databases perform the documented migration-table rename before
  migration. `migrate --pretend` still creates migration bookkeeping, so it is
  unsafe before that rename; `migrate:status --pending` is read-only. Follow the
  [README deployment procedure](../../README.md#deployment) and go-live checklist.
- Production setup, data rehearsals, external delivery/payments, gateway activation,
  fetch-button policy, finance/impersonation grants, accepted risks and search-engine
  setup remain owner/system decisions. Repository checks cannot certify those.
- Keep held script/WAF, order-tax and global image-policy decisions above. Retained
  legacy uploader deletion failure handling, missing legacy API/seller handlers,
  import-created external-upload ownership and POST verification-status repetition
  remain separately scoped concerns. The storefront conversation validator's
  character-only bound remains separate from the seller byte-bound repair.
- The seller dashboard's rolling30/60-day totals retain Current month/Last Month
  labels; its mobile API aggregate is separate. A recorded responsive withdrawal
  message visibility failure is historical and was not resolved by the dashboard
  repair. Reproduce against current code before changing behavior.

## Verification limits

Historical results establish only the recorded tree, runtime, cases and conditions.
Syntax/Blade compilation is not runtime, PDF, browser or visual parity. SQLite
passes do not prove MySQL locking/collation/FK behavior; isolated MySQL probes are
not production rehearsal or concurrent HTTP load. Permission definitions do not
prove production grants. Browser workflows do not establish globally clean
console/network output or external service delivery.

The newest audit reports **217 findings** (A/B/C/D/E/F =36/14/38/7/99/23) and
back-office baseline **55** at its recorded snapshot. Runtime names, component
controls, script-submitted forms and route-dependent matching have documented
scanner limits; adjudicate findings against source and rendered tests. Do not
remove a live baseline entry or describe a scanner limitation as a fixed field.

The 2026-10-26 editor CI incident was **not reproduced** and its failed trace was
unavailable (403). Selection-readiness changes and successful repeated browser
runs support a narrower claim than proving the original CI cause. No component
accessibility defect was demonstrated. Keep exact expected browser/Tiptap text
and matching selections in the shared helper; do not weaken assertions, retries
or timeouts. Historical full gates did not run all application browser tests,
all separate MySQL cases, advisory audits or deploy-script tests locally.

## History index

Use the [original section index](../archive/store-backoffice/README.md) to resolve
numbered references across the dated archives.

The [reconciliation ledger](../store-backoffice-reconciliation-20261011.md) is the
canonical historical source-disposition, runtime/troubleshooting and verification
account through step41. The archives below preserve detailed implementation
contracts and later dated task evidence. Earlier “pending” and “next” statements
remain historical where later sections supersede them.

- [Implementation/task evidence, 2026-10-12](../archive/store-backoffice/changes-20261012.md)
- [Implementation/task evidence, 2026-10-13](../archive/store-backoffice/changes-20261013.md)
- [Implementation/task evidence, 2026-10-14](../archive/store-backoffice/changes-20261014.md)
- [Implementation/task evidence, 2026-10-15](../archive/store-backoffice/changes-20261015.md)
- [Implementation/task evidence, 2026-10-16](../archive/store-backoffice/changes-20261016.md)
- [Implementation/task evidence, 2026-10-17](../archive/store-backoffice/changes-20261017.md)
- [Implementation/task evidence, 2026-10-18](../archive/store-backoffice/changes-20261018.md)
- [Implementation/task evidence, 2026-10-19](../archive/store-backoffice/changes-20261019.md)
- [Implementation/task evidence, 2026-10-20](../archive/store-backoffice/changes-20261020.md)
- [Implementation/task evidence, 2026-10-21](../archive/store-backoffice/changes-20261021.md)
- [Implementation/task evidence, 2026-10-22](../archive/store-backoffice/changes-20261022.md)
- [Implementation/task evidence, 2026-10-23](../archive/store-backoffice/changes-20261023.md)
- [Implementation/task evidence, 2026-10-24](../archive/store-backoffice/changes-20261024.md)
- [Implementation/task evidence, 2026-10-25](../archive/store-backoffice/changes-20261025.md)
- [Implementation/task evidence, 2026-10-26](../archive/store-backoffice/changes-20261026.md)
- [Foundation and integration snapshot, 2026-10-07–20](../archive/store-backoffice/foundation-and-integration-20261007-20261020.md)
- [Reconciliation, 2026-10-11–12 (canonical ledger links)](../archive/store-backoffice/reconciliation-20261011-20261012.md)
