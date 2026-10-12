# Store back-office reconciliation — 2026-10-11

Integration and audit handoff; current results are recorded in
[final all-store browser verification](#final-all-store-browser-verification-2026-10-12)
below. The browser gate has passed. Earlier integration and blocked attempts
remain historical evidence. Original integration branch:
`codex/store-backoffice-reconciliation-20261011-01`, worktree:
`/workspace/store-backoffice-reconciliation-20261011-01`, based on latest fetched
`origin/develop`, `8e3f82b60b2b4dc951b97ccfed985a75e9e6be69`.
The original `/workspace/warehouse_management` checkout is preserved. Scratch,
runtime setup scripts and check logs stay outside the repository under
`/workspace/work/store-reconcile-runtime`. No push or deployment was performed.

## Source disposition

All fourteen available source tips were verified against the supplied full
references during inventory. Integration uses selected cherry-picks in the
requested order, resolving behavior and consumers rather than choosing entire
conflicting branches. A cherry-pick's presence does not mean all its changes
were activated. The table and deferred paths below define actual scope.

| Source branch | Exact source commit | Disposition | Initial integration commit / rationale |
| --- | --- | --- | --- |
| `codex/store-backoffice-foundation-verification-20261011` | `67b621635e0cd290ddfa7aa3f43889f4cf17cf6f` | Included, documentation reconciled | `3ff704ff1b09d6699f5e36f2c505c6a3ae4bff83`; foundation constraints retained, historical progress replaced by actual integrated inventory |
| `codex/seller-admin-prerequisite-review-20261011` | `1c005f6efdddee958ba8d739afd35eb0935007bd` | Included, documentation reconciled | `b234db6880b0153938d5b4c56e10f0716eb5bbcc`; seller feature-use prerequisite remains unresolved |
| `codex/store-backoffice-audit-2026-10-11` | `2062177776722e3dfb37ae968b8c9b0aeeba67f7` | Excluded as a separate integration source | Ancestor with no unique code contribution; no independent audit-code merge |
| `codex/store-backoffice-reference-config-2026-10-11` | `35ae3133e0cf6f1d6f938eda5e01fdebe695d98e` | Included, canonical | `9645e25d541b6a219816697b5f95a5f3c8226a6b`; reference/shipping forms, controllers and routes selected |
| `codex/store-backoffice-language-settings` | `465578796ff01c47276c24d5d8e504ce04198d36` | Included, adapted | `9b61a38915d4b62d50119bf8b95e7d8fed7e2fb0`; distinct language CRUD and translation-selector validation |
| `codex/store-backoffice-reports-notifications-20261011` | `7970a407ce4c96384c9d572bcf7e3065bd19234a` | Included, adapted | `b4f7bd62e60887b6e2aa2403a06fc5cd6a6b8990`; selected report and notification controllers |
| `codex/store-uploads-profile-migration` | `944e2e1ed50fd50a0d01ac48029405be43879c5d` | Included, canonical contract adapted | `6c21e5a482f98dd1df705cdcd05e5f2c8b70c62d`; normalization confined to selected admin path, legacy callers preserved |
| `codex/store-backoffice-customers-representatives` | `218f05f15693a06f748b9d20b799cad5f8a75e81` | Partially included | `99a14828b557c36236cf6dea5c697ad85758de38`; customers/addresses/representatives/packages/payments/visit list; classified activation held; customer packages pending |
| `codex/store-catalogue-admin-migration` | `143c5693ab03a6d3debd101cbb29b426f9ae9c76` | Included implemented subset, adapted | `29ada8cd505074f696e6675487f6ccbc27d181da`; bounded translated search, attributes/values/colors/discount/reviews/bulk/offers; core catalogue conversion remains pending |
| `codex/store-website-support-migration-20261011` | `e3995898cd53c166968d9804fd456fe935579fcf` | Partially included | `d08097341869376bf8654e9135af6f0eda10bf59` plus follow-up; header/home/conversations/queries/ticket list and typed reply, Blade detail; editor/script-policy activation held |
| `codex/store-marketing-blog-migration` | `cf5ef305e905097e6b0950c85ec69b0aa8d54e3b` | Partially included | `23c7619fe5a81412afb1b3ae9f00b016eab5e266` plus follow-up; marketing, blog category/list/actions and typed newsletter send/recipient lookup; retained Blade editors |
| `codex/store-logistics-partial-migration-2026-10-11` | `468d235ca125f157a0d854ecc1b4dfcce279c7ed` | Included implemented subset, adapted | `de582875f8e329e54123c0629658acb26cd88117`; drivers/trucks and compatible hardening, retained Blade trips/maps and customer visit list |
| `codex/store-orders-dashboard-2026-10-11` | `7ab019263fb96d41c497174d0e9d450ff8062dff` | Partially included | `3bece94c1e25f6f5e91606238a1c2fd6b64fd896`; orders/entry and shell utilities, dashboard/charts and proposed tax arithmetic held |
| `codex/store-backoffice-shipping-tax-batch-1` | `e552333810f4405de4c9ed2fcbd3d91df9083eb3` | Superseded implementation; partially included independent tests/fix | No duplicate controllers/pages/admin routes; follow-up adapts `StoreBackOfficeShellTest`, currency switcher regression and missing-currency handling |
| Unavailable audit | `aed3feab5b6edd95fb941ee92a455e058403def5` | Explicitly excluded | Unavailable; not recovered or included. Independently reconstructed in prompt 3 below; no source commit recovered |

Follow-up reconciliation commit: `70de4ed8513087c7c8a0c941360113d2f90737f2`. It records the exact marketing,
website/support and shipping-test source commits in its trailers and preserves
their deferred activation boundaries.

## Contract and conflict resolutions

- Canonical reference forms retain their route names. Catalogue's bounded,
  translated `ProductSearchController` serves `order_configuration.product-search`
  and safe attribute-option consumers. Duplicate shipping code is superseded.
- `ValidatesStoreLanguage` retains translation-selector `languageRules`;
  `ValidatesStoreLanguageDefinition` owns language CRUD uniqueness/definition
  rules. One `StoreBooleanField` API uses `name`, `value` and `disabled`.
- Dedicated settings endpoints and the retained generic writer reject migrated
  reference/environment/header/home/script/map keys before any write, including
  localized payloads. Seller commission toggles use a dedicated `{type,value}`
  endpoint allowing only its two activation keys; commission values have their
  own narrow contract. No broad settings writer is restored.
- Selected upload DTO is `id,name,type,size,url`. Consumers adapt CSV IDs,
  initial files, request keys, required behavior and metadata. Delete and bulk
  delete preserve ownership, action gates, atomic preauthorization, confined
  storage paths and failure handling. Local storage URLs resolve consistently;
  resource URLs reject unsafe protocols. Legacy seller/API/Blade upload routes
  remain; private offers/import storage remains separate.
- Admin raster normalization is opt-in at the service call. Existing API/seller/
  trip optimization and format behavior stays established; global image policy
  and SVG changes are not inferred from admin integration approval.
- Visit list/filter contracts come from the customer branch (`date_from`,
  `date_to`, singular `delete_representative_visit`); compatible detail/day maps
  use hardened Blade handlers. Trips/maps are hardened, not counted converted.
- Profile/notification links use selected Inertia controllers. Public/customer/
  seller consumers remain. Ticket detail and blog create/edit links explicitly
  use full loads into retained Blade editors, while independent typed actions
  use per-action gates and HTML sanitation. Ticket reply authorization now
  matches its existing `reply_to_support_tickets` button gate.
- Permission definitions are unioned without automatic role grants. A regression
  verifies new definitions do not authorize an existing staff role. Backend
  action gates remain independent of menu visibility and store/WMS guards remain
  isolated. Source fixtures are adapted to exact permissions and owned uploads.
- Relations/casts, resources, upload types, pagination, downloads, shared fixtures,
  English/Arabic translation leaves and Inertia stubs are combined additively.
  `ResourceFormPage` has one `after-form` slot inside the width wrapper outside
  its Form. Playwright keeps isolated database setup and no server reuse, plus
  compatible source seed fixtures; configuration discovery is checked separately
  from browser execution.
- Removed exclusions apply to migrated/cleaned handlers only. Retained public,
  seller, trip, website and dashboard Blade handlers keep needed exclusions. Newsletter and storefront currency handlers are cleaned and their exclusions removed.
  Public blog category eager loading and missing-currency 404 are independent
  fixes, covered without taking duplicate infrastructure.

## Deferred source changes and owner decisions

Paths are relative to the repository root. Each source reference is the full
commit in the source table above; short references below identify those same
objects. These changes remain available in Git and have not been silently lost.

| Source / paths or symbol | Established behavior retained | Proposed activation / prerequisite |
| --- | --- | --- |
| `218f05f1`: `app/Http/Controllers/Store/Admin/ClassifiedProductController.php`, classified requests/resources/pages/tests and routes | Root `CustomerProductController`, classified Blade and feature flag remain | Classified migration requires feature-use approval; no applicable evidence located |
| `1c005f6e`: seller prerequisite review; existing seller controllers/views | Seller administration and seller-facing Blade area remain | Establish seller feature usage or explicitly authorize scoped removal; feature flags are not evidence |
| `e3995898`: `resources/js/components/StoreRichTextField.vue`, its tests and Tiptap additions in `package.json` / `package-lock.json` | Existing Blade editors and their assets remain | Preferred shared editor basis after independently verified Tiptap dependency approval; checked-in claims alone were not accepted |
| `e3995898`: `Store/Admin/WebsiteFooterController.php`, `WebsiteAppearanceController.php`, `WebsitePageController.php` and corresponding requests/resources/Vue forms/pages/tests | Footer/appearance/custom-page Blade GET and existing writers remain | Editor-dependent Inertia activation after editor approval and consumer adaptation |
| `e3995898`: `Store/Admin/SupportTicketController::show` Inertia implementation, `StoreTicketReplyForm.vue`, `Store/Admin/Support/Tickets/Show.vue` and editor-dependent frontend tests | Ticket list and typed reply integrated; existing sanitized Blade detail/reply editor remains | Vue detail/reply editor activation after shared editor approval; useful backend sanitation/authorization tests adapted now |
| `e3995898`: `Store/Admin/WebsiteScriptsController.php`, script requests/form, `2026_10_10_000001_add_manage_website_scripts_permission.php`, `config/waf.php` / associated policy expectations | Existing stored script rendering and appearance permission/WAF remain; compatibility endpoint allows only two script keys | Decide custom-script permission/rendering policy and rich-text WAF exceptions before activation; generic writers already reject script keys |
| `cf5ef305`: `StoreRichTextField.vue` / duplicate uploader infrastructure and competing upload request/resource changes | Canonical uploader and future shared `e3995898` editor basis selected | Duplicate infrastructure superseded; useful blog formatting/validation tests retained/adapted |
| `cf5ef305`: `StoreBlogFormFields.vue`, `Store/Admin/Blog/Posts/Create.vue`, `Edit.vue` and editor-dependent tests | Inertia blog list/typed actions with retained Blade create/edit editors; public rendering stays Blade | Migrate forms after shared editor approval; no unrelated `eslint-plugin-vue` or `concurrently` upgrades |
| `cf5ef305`: `resources/js/pages/Store/Admin/Marketing/Newsletters/Index.vue`, `StoreNewsletterFormFields.vue`, `StoreNewsletterRecipients.vue` and editor-dependent frontend tests; source controller’s Inertia index | Existing newsletter Blade editor; typed send/recipient search integrated; selected SMTP settings endpoint remains | Vue newsletter/editor activation after shared editor approval; SMTP retains both `smtp_settings` and `send_newsletter` gates |
| `468d235c`: unconverted `TripController` / visit map Blade consumers | Existing Leaflet/Google assets, hardened coordinates/DOM output and HTTPS routing; existing departure ID 9 semantics retained | New Vue Leaflet dependency and departure business policy require decisions; no map conversion claimed |
| `7ab01926`: `Store/Admin/DashboardController` chart implementation, `Store/Admin/Dashboard/Index.vue`, `StoreDashboardCharts.vue`, chart tests and Chart.js dependency additions | Root Blade dashboard with existing chart assets; shell counts/search/notifications and scoped cache clear integrated | Chart.js dependency approval before Inertia dashboard activation; checked-in claim is not independent evidence |
| `7ab01926`: `app/Services/StoreAdminOrderService.php` line tax/grand-total arithmetic and corresponding UI/test expectations | Raw product tax metadata stored; grand total sums line prices, as existing entry/edit handlers did | Percentage tax conversion and adding tax/shipping/subtracting coupon require business approval; regression expectations reflect established behavior |
| Sources touching `seller_app_link`, SVG, shared logout/session or server status | Existing behavior and routes/templates remain | Explicit owner decisions before removal or shared policy changes; admin conversion alone is insufficient |
| `e5523338`: duplicate reference controllers/requests/pages/routes/translations/progress | Selected `35ae3133` implementation remains | Superseded, not awaiting activation; retain only independently useful adapted tests/fixes |

No independently verified approvals were found for the above dependency or
business-policy changes. Integration authorization is not treated as such
approval. No new dependencies were installed and manifests/locks are unchanged.

## Focused verification and runtime

Host PHP/Composer were absent. Provisioned a PHP 8.5 Docker runtime using the
existing cloud PHP image and repository/environment guidance, including GD WebP
support. Composer platform requirements passed against the existing lock and
installed vendor versions. Existing dependencies were copied into the separate
worktree; no platform requirements were bypassed and no locks were rewritten.
An isolated local environment/app key and SQLite test database are used; no
production or original-checkout database migration was run.

During integration, focused checks passed after resolving contract/fixture
failures: reference/settings/reports; canonical uploads/profile and legacy upload
compatibility; customers/representatives; catalogue; website/support; marketing;
logistics; orders and retained dashboard. Representative passing runs include:

- Upload/profile/customer/representative/legacy upload subset: 324 tests passed.
- Website/support adapted subset: 240 tests passed; 63 frontend tests passed.
- Logistics focused run: 466 passed, one ownership-fixture failure; correcting
  that fixture gave 37/37 truck tests. Logistics frontend: 42 passed.
- Order/shell frontend: 64 passed; backend reruns passed all affected cases after
  preserving tax expectations and replacing stale legacy handler assertions.
- Blog/ticket/public/shell follow-up: 134 passed, one retained-layout assertion;
  corrected shell/permission regressions then passed 2/2. Blog/menu frontend:
  17 passed. Typed newsletter/analysis-exclusion follow-up: 27 passed.
- Resulting shell/menu/blog/order-entry frontend subset: 34 passed (four files).
- Resulting TypeScript and PHPStan checks passed (PHPStan: zero errors). Pint
  passed; ESLint passed for 299 changed JS/TS/Vue paths. Prettier checked those
  paths, found one import-format issue, and that corrected file passed recheck.
- Production assets built successfully after the newsletter follow-up. Playwright
  discovery succeeded: 58 tests in 36 files; browsers were not executed.
- `git diff --check` passed. Frontend/Composer manifests and lockfiles are identical
  to the develop base. No `work/` scratch files are tracked.

These are progressing focused runs, not aggregate totals or final combined
verification. PHPStan, TypeScript, formatting/lint and production asset checks
are run for the resulting change. Browser fixtures/configuration do not imply
browser tests ran. Playwright execution, final combined tests and production
MySQL behavior remain for later verification. Runtime setup now supports PHP
checks; native host binaries remain absent. No Claude hooks were assumed to run.

## Remaining implementation and handoff

- Customer packages and core catalogue brands/categories/products/digital pages
  remain unimplemented in the selected migration sources.
- Website footer/appearance/custom pages, ticket detail/reply Vue editor, blog
  editor forms/newsletters and dashboard conversion remain held as above.
- Trip/map conversion remains unimplemented; seller/classified scope remains
  decision-blocked. Production role grants require explicit review.
- Keep legacy admin shells/assets/includes/CSP exceptions while consumers remain;
  keep PDF layouts/downloads, `resources/views/app.blade.php`, storefront/seller/
  authentication `HandleStoreBladePages` and `store.blade_csp`.
- The explicitly unavailable audit is independently reconstructed below. Perform final
  combined verification later; do not treat this handoff as deployment approval.

The [migration guide](rules/store-backoffice-migration.md) lists actual retained
Blade files and registered migrated controllers/routes, without sibling progress
counts. Selected cherry-picks and follow-up reconciliation commits are preserved
on the integration branch for the next prompt.


## Independent reconstructed audit — prompt 3

Verified branch/worktree/clean status before edits at
`29e5e2527bf7f9920436e5970a4413c46325ed03`; HEAD remains unchanged. Changes in
this section are uncommitted for prompt 4. Original checkout preserved; no
commit, dependency installation, push, deployment, live email or payment call.
Missing source `aed3feab5b6edd95fb941ee92a455e058403def5` remains excluded.
This section reconstructs evidence from actual code, fixtures and execution.

### Findings and exact references

| Finding | Disposition and evidence |
| --- | --- |
| Blade views with `page` data mistaken for Inertia | Confirmed at starting HEAD: policy Inertia GET returned 200 instead of 409; CMS/policy CSP handling depended on the same incorrect variable check. Fixed `app/Http/Middleware/HandleStoreBladePages.php` to identify the actual `app` root view. `ReconstructedMigrationAuditTest` covers policy/CMS CSP and fallback; existing `HandleStoreBladePagesTest` covers genuine initial/AJAX Inertia responses. |
| Retained uploader URLs | Confirmed all four backend/frontend/seller layout metadata used `getBaseURL()`, which drops `/temp` and request port. Changed their `app-url` metadata to `url('/temp')` and `file-base-url` to existing `my_asset('')`, correcting obsolete `/public/` and dropped-port previews; shared helpers/endpoints and seller consumers remain. Scheme/port regression test plus HTTPS legacy upload/selection/attachment-removal workflow; physical deletion uses the selected admin endpoint and asserts its exact JSON response. Thumbnail loading verifies the file URL contract. |
| CMS `tags`/`meta_img` failure | Reproduced `MissingAttributeException` on `tags`. Schema/model use nullable `keywords`/`meta_image`; fixed `resources/views/shop/frontend/custom_page.blade.php`. Tests exercise populated and null metadata and public content; baseline CMS 500 becomes current 200. |
| Representative receipt QR compilation | Already resolved by selected `218f05f15693a06f748b9d20b799cad5f8a75e81`, integrated in the customer/representative subset. `resources/views/shop/frontend/user/export-rep-payments.blade.php:98` uses escaped XML-declaration regex. Compilation/lint and real signed receipt PDF rendering pass; no new receipt edit. |
| VoguePay currency syntax | Reproduced unmatched `)` in generated PHP. Fixed `resources/views/shop/frontend/voguepay/customer_package_payment_vogue.blade.php` to read configured Currency `code` directly. Render test asserts amount/currency without executing payment JavaScript or contacting payment services. |
| Browser repeatability | Demonstrated unquoted seeder namespace losing shell backslashes; quoted `Tests\Fixtures\StoreCampaignBrowserSeeder` in `playwright.config.ts`. Old Arabic setup omitted required `rtl` and targeted removed Blade inputs. Idempotent fixture and current selectors replace it. Staff coexistence targeted now-Inertia notifications; it now targets retained carriers, enabling only existing shipping menu settings in the isolated fixture database. Single worker, zero retries and dedicated four SQLite files remain. Environment overrides use installed Chromium/HTTPS; graceful shutdown avoids orphaned Docker test servers. |
| Cell fixture collision | Normal single run passed, but deterministic 100-seed sweep reproduced four unique `rows.letter` violations at seeds 27, 40, 45, 69. `CellTest` manually inserted Z while implicit lazy RowFactory generation could select Z; Faker uniqueness does not reserve explicit overrides. Last test now uses generated row and covers the four reproduced seeds. Factories remain lazy and unchanged. |
| Representative web visit-note no-op | Additional confirmed integration regression: starting HEAD root `VisitController::update_note` did nothing, contrary to established develop `8e3f82b60b2b4dc951b97ccfed985a75e9e6be69` behavior; existing ownership test failed. Restored scoped store-actor write with new `UpdateRepresentativeVisitNoteRequest`; foreign visit returns 0, own update 1, malformed notes/IDs are refused before mutation. API behavior is unchanged. |
| Product-query read aliases | Additional confirmed integration regression from selected website/support source `e3995898cd53c166968d9804fd456fe935579fcf` / integration `d0809734`: `/temp/product-queries` index/show still referenced deleted root methods. Restored narrow permission-gated redirects to selected admin controller, with encrypted legacy ID validation. Customer submission and seller controller remain separate. Regression tests exercise staff, refused customer reads and invalid ciphertext. |

New regression file: `tests/Feature/Store/ReconstructedMigrationAuditTest.php`.
Related edits are the two root controllers, middleware, one new FormRequest,
four layout files, CMS/Vogue templates, `CellTest`, Playwright configuration,
RTL fixture helper, three existing e2e specs and new legacy-uploader spec.

### Integrated-scope contracts audited

This pass covers reference/configuration, language/settings, reports/notifications,
uploads/profile, customers/representatives, catalogue subset, website/support,
marketing/blog, drivers/trucks/retained visit detail, orders and shell consumers.
It does not treat scope as only staff/roles.

- Selected `UploadResource` uses `id,name,type,size,url`; `StoreUploadField` uses
  CSV IDs/initialFiles and canonical request contracts. Action permissions,
  ownership, atomic bulk preauthorization, confined paths, storage-failure handling,
  unsafe-protocol rejection and private offer/import separation pass focused tests.
  Image normalization/SVG/shared-service policy remains an owner decision.
- `ValidatesStoreLanguage` keeps translation selector rules; distinct
  `ValidatesStoreLanguageDefinition` preserves CRUD uniqueness/default-code rules.
  `StoreBooleanField` consistently uses `name,value,error,disabled`. Frontend and
  backend callers/tests pass. `ResourceFormPage` slot, pagination, localized
  resource/translation consumers and selected profile/notification layout links
  are covered by existing focused tests and type checks.
- Dedicated settings allowlists, map/script compatibility and retained writer
  reject reserved plain/localized keys before writes. Seller commission keeps
  its narrow `{type,value}` activation endpoint. No broad writer/privilege grants
  were restored. `StoreIntegrationPermissionsTest` proves new action definitions
  do not grant staff/existing roles access. Production role grants remain unverified.
- Store/WMS guard tests and shared narrow props pass. Order tests explicitly
  preserve tax 21 on new and edited unit lines rather than activate proposed
  tax arithmetic. No departure/logout/session/server-status policy changes.
- Literal Inertia page references resolve; reports use a dynamic suffix and
  their registered report cases pass runtime tests. Route reflection starts with
  1,055 registered `/temp` web/API routes. None of the selected Store controllers
  have missing action methods. Two newly missing root read aliases were fixed;
  the remaining 44 missing methods are inherited retained API/seller/resource scaffolding,
  not new conversion regressions. No exclusion broadening was introduced;
  `StaticAnalysisExclusionsTest` and PHPStan check the retained boundary.

### Runtime, syntax, render and browser evidence

Docker PHP runtime: `store-reconcile-php:8.5` (PHP 8.5.11, GD/WebP), existing
vendor/node_modules and unchanged manifests/locks. Host PHP is absent; no Claude
hooks/platform bypass/install used. Main wrapper:
`/workspace/work/store-reconcile-runtime/bin/php`.
For browser servers, wrapper `audit/bin/php` under that runtime directory forwards
explicit isolated DB/APP_URL/CSP/proxy variables into Docker `--network host` and
stops its own named container on TERM. It mounts only the integration worktree,
common Git metadata read-only and external runtime artifacts.

Executed focused groups (overlapping runs; do not sum as a combined suite):

- Reconstructed fixes, Cell collision seeds, middleware and representative payment
  links: 35 passed / 117 assertions at first fixed state.
- Upload/profile/notification/language/settings/reference/order configuration,
  guard isolation, narrow props, permission grants, analysis exclusions, invoices,
  bulk-upload/download contracts and representative payments: 522 passed / 2,497
  assertions (`audit/backend-contracts.log`).
- Retained storefront/auth/seller shell, reference PDF view, verification/newsletter
  email templates, VoguePay, order-tax behavior, newsletters and legacy route
  middleware/reachability: 131 passed / 644 assertions (`audit/backend-retained-final.log`).
- Customers/addresses/notifications, representative packages/visits/detail,
  drivers/trucks, reports, attributes/colors/offers/product search, website
  header/home, support authorization, blog and public marketing rendering:
  661 passed / 3,709 assertions (`audit/backend-scope.log`).
- Final alias/visit-validation, product-query permissions, representative payment
  ownership and four Cell collision seeds: 57 passed / 254 assertions
  (`audit/backend-aliases-final.log`).
- Seven frontend contract files: 47 tests passed (boolean field, language fields/
  tabs, upload field/manager, ResourceFormPage and admin menu).
- All 255 current Blade templates compiled with the Laravel compiler into external
  temporary files and each generated PHP file passed `php -l`; initial run had
  exactly one failure, the VoguePay expression. This is syntax coverage, not a
  claim all 255 rendered with all data variants.
- Runtime render samples include storefront/auth/admin HTTP views, authenticated
  seller shell, email templates without delivery, reference brand PDF HTML,
  genuine invoice PDFs and signed representative receipt PDFs, and VoguePay
  configured-currency template. Existing `clean_html()` is preserved and email
  regression checks valid formatting survives while script content is removed.
- TypeScript, PHPStan (zero errors), Pint, changed-spec ESLint, Prettier and
  production Vite build passed. Existing retained-handler formatting/analysis
  exclusions are unchanged. Optional `fontaine` build
  advisory does not authorize installing dependencies.

Installed `/usr/bin/chromium` and `/usr/bin/ffmpeg` were used, with an external
ffmpeg cache symlink; no download/installation attempted. Managed Chromium
blocks data: URLs; that policy was preserved and HTTP(S) tests executed.
Local HTTPS proxy at `https://127.0.0.1:9443` forwards only to local HTTP 8010;
its test certificate/key stay outside the repository. Only loopback is trusted
in the fixture environment. CSP is enforced (`CSP_REPORT_ONLY=false`), with
existing Blade exceptions and strict Inertia policy preserved. Commands:

```sh
PATH=/workspace/work/store-reconcile-runtime/bin:$PATH npm run types:check
/workspace/work/store-reconcile-runtime/bin/php artisan test --compact <focused-files>
/workspace/work/store-reconcile-runtime/bin/php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
/workspace/work/store-reconcile-runtime/bin/php vendor/bin/pint --dirty --format agent
PATH=/workspace/work/store-reconcile-runtime/bin:$PATH npm run build
PATH=/workspace/work/store-reconcile-runtime/audit/bin:$PATH PLAYWRIGHT_BROWSERS_PATH=/workspace/work/store-reconcile-runtime/audit/browser-cache PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/usr/bin/chromium PLAYWRIGHT_BASE_URL=https://127.0.0.1:9443 TRUSTED_PROXIES=127.0.0.1 npx playwright test tests/e2e/store-reference-coexistence.spec.ts tests/e2e/store-staff-coexistence.spec.ts tests/e2e/store-uploads-profile.spec.ts tests/e2e/store-reference-pagination-rtl.spec.ts tests/e2e/store-legacy-uploader.spec.ts --output=/workspace/work/store-reconcile-runtime/audit/browser-results
```

Intermediate browser failures are retained in logs: actual seeder quoting error,
missing Playwright ffmpeg location (resolved using installed ffmpeg), orphaned
owned Docker server (stopped; graceful shutdown added), stale notification/language
selectors, absent brands schema field, an audit build running concurrently
with browser tests briefly removing the Vite manifest, and a trace-confirmed
`net::ERR_NETWORK_CHANGED` while unrelated Docker checks created network
interfaces. Repeat browser runs use stable completed assets, no concurrent Docker
checks, no retries and fresh dedicated databases. A read-only thumbnail assertion
was added after the first passing run to verify the corrected preview URL too.

### Pre-pilot public HTML and SEO comparison

Available baseline `cdf719789541079ce86c5dc1b605520aa1ab7c9f` runs in isolated
`/workspace/store-backoffice-prepilot-audit-20261011-01`, detached; original
checkout remains untouched. Composer manifest/lock and frontend manifest match;
baseline historical frontend lock differs from installed compatible packages.
Both production builds succeed using existing packages, no installs or lock edits.
This limits exact historical frontend dependency equivalence, not backend schema
or initial HTML comparisons.

Separate `audit-public.sqlite` and ancillary databases, deterministic category,
brand/product/stock/CMS/currency/settings fixtures, same application key/local HTTPS origin
and copied baseline fixture database plus current migrations were used. Laravel
HTTP-kernel GET responses were captured before any hydration/browser JavaScript;
these are initial response HTML captures, not remote production crawls. Compare
visible text and crawlable href sets plus metadata, statuses/redirects; exclude
CSRF tokens and operational `app-url`/`file-base-url` changes from equivalence claims. CSP
headers differ as expected: the baseline applied its global strict policy to
retained Blade views; current coexistence uses established Blade exceptions.
The current browser workflows enforce those policies.

| Sample | Baseline → current status | Initial HTML comparison |
| --- | --- | --- |
| Home, product, category, search, deals, brands, policy, login | 200 → 200 | Primary text, crawlable links and SEO/social metadata identical |
| CMS | 500 → 200 | Baseline failed on nonexistent metadata attributes; current valid content/metadata renders |
| Missing product | 404 → 404 | Response content preserved |
| Admin redirect | 302 → 302 | Same login Location and response content |

Inherited findings still exist: category/search/deals/brands use homepage
`og:url`; CMS/policy `og:url` omit `/temp`; login declares `robots=index, follow`.
No unrelated SEO fix applied. Sampled successful pages have no canonical link,
JSON-LD or itemscope structured data in either tree. Product itemprop metadata
exists and is preserved; do not call it a complete structured-data implementation.
Baseline CMS metadata is unverifiable because its response is 500. Dynamic CSRF
values are explicitly excluded; scheme/port/temp uploader and file-base corrections are intentional.

External artifacts: `/workspace/work/store-reconcile-runtime/audit/` contains
compile JSON/generated PHP, exact command logs, 11 HTML captures per tree,
`public-comparison.json`, route reflection outputs and browser failure traces.
They are not committed or tracked scratch files. Isolated generated assets,
SQLite/cache files and fixture uploads are not proposed source changes.

### Outstanding retained issues, decisions and verification limits

- `resources/views/shop/backend/product/brands/index.blade.php` reads nonexistent
  `brands.order_level`; a populated browser fixture demonstrates a 500. Baseline
  schema lacks that column too. Core catalogue remains deferred; no speculative
  schema/business-field change made. Public brand listing works in comparison.
- Populated retained dashboard `resources/views/shop/backend/dashboard.blade.php:147`
  queries products without `product_translations`; strict lazy-loading causes 500
  when translated names are rendered. Its query is unchanged from the baseline.
  This is recorded as retained rendering debt, not new dashboard activation; the
  coexistence test uses working retained carriers. Dashboard conversion remains held.
- Remaining missing action methods in retained API/seller resource declarations
  require separately scoped review. A reflection check proves references, not
  browser reachability or approval to activate those features. Exact remaining
  URI/action pairs are in external `audit/routes-final.json`; comparison with
  `audit/routes-baseline.json` finds no newly missing methods after alias fixes.
- Legacy `AizUploadController::destroy` catches storage failure and still deletes
  the row; its paths/failure semantics are weaker than selected admin upload
  handling. Legacy preview external-link rendering also warrants unsafe-protocol
  review. The modal delete UI is commented out and its old JS destroy URL has no
  route; physical fixture deletion uses selected `uploaded-files.destroy`. These retained security concerns are recorded separately; this pass
  does not silently change shared seller/storage policy.
- Production MySQL behavior, deployed role grants, external services and real
  payment/email delivery are not executed. Browser coverage is the listed local
  Chromium workflows, not all pages/engines/devices. Baseline comparison is local
  deterministic initial HTML, not real production crawl coverage.
- Tiptap/Chart.js/Leaflet, custom-script policy, seller/classified feature use,
  customer packages/core catalogue, maps, image/SVG normalization, new-order tax,
  departure/logout/session policy, seller app links/server status and dependency
  approval gaps retain their existing established behavior and deferred proposals.
  No approval inferred from checked-in claims and no production privileges granted.
- Prompt 4 still needs final combined verification. All reconstructed source fixes
  remain reviewable and uncommitted on the integration branch.


### Reviewable changed source files

The following files remain uncommitted (generated uploads/assets/cache/databases
are excluded from source changes):

- `.ai/rules/store-backoffice-migration.md`
- `.ai/store-backoffice-reconciliation-20261011.md`
- `app/Http/Controllers/ProductQueryController.php`
- `app/Http/Controllers/VisitController.php`
- `app/Http/Middleware/HandleStoreBladePages.php`
- `playwright.config.ts`
- `resources/views/shop/backend/layouts/app.blade.php`
- `resources/views/shop/backend/layouts/layout.blade.php`
- `resources/views/shop/frontend/custom_page.blade.php`
- `resources/views/shop/frontend/layouts/app.blade.php`
- `resources/views/shop/frontend/voguepay/customer_package_payment_vogue.blade.php`
- `resources/views/shop/seller/layouts/app.blade.php`
- `tests/Feature/Models/CellTest.php`
- `tests/e2e/store-reference-pagination-rtl.spec.ts`
- `tests/e2e/store-staff-coexistence.spec.ts`
- `tests/e2e/store-uploads-profile.spec.ts`
- `tests/e2e/support/storeFixtures.ts`
- `app/Http/Requests/Store/UpdateRepresentativeVisitNoteRequest.php`
- `tests/Feature/Store/ReconstructedMigrationAuditTest.php`
- `tests/e2e/store-legacy-uploader.spec.ts`


### Final focused handoff results

- HTTPS/enforced-CSP Chromium workflows: 7/7 passed in each of two fresh-database
  runs (`audit/browser-workflows-final.log`, `audit/browser-workflows-repeat.log`).
  The repeat includes the latest thumbnail assertion and exact `{deleted: id}`
  response check. Executed: direct visits, Inertia-to-Blade carrier navigation,
  Blade-to-Inertia staff navigation, logout redirect to Blade login, English/Arabic
  pagination direction, RTL picker focus/dark contrast, legacy upload/selection/
  attachment removal and canonical physical deletion, Vue upload rejection/avatar
  selection/validation retention/native delete confirmation. No retries added.
- Latest reconstructed backend group: 57 passed / 254 assertions. Latest 255
  Blade compile/PHP-lint run: zero failures. Production build, TypeScript,
  PHPStan (zero errors), Pint, changed e2e ESLint and Prettier passed;
  `git diff --check` passed. Retained handler exclusions remain unchanged.
- Latest public captures/comparison include both intentional uploader metadata
  corrections. SEO/status/content conclusions above remain the same.
- Intermediate orphan fixture uploads were preserved outside the source tree at
  `audit/orphaned-browser-uploads`; no existing checkout data was discarded.
  Only newly generated testing-disk directory permissions were made readable for
  host ESLint after Docker fixtures created restrictive cache directories. No
  production filesystem policy changed. The owned local HTTPS proxy and test
  server were stopped; no deployment or external transaction took place.
- At the end of prompt 3, the documented Docker/installed-browser harness had
  no local PHP/browser blocker. Prompt 4 rechecks current access below. Production MySQL/grants/external services, exact
  baseline frontend lock equivalence, unconverted-module issues and owner decisions
  remain limits. This is focused audit verification, not prompt 4's final combined
  verification. HEAD stays `29e5e2527bf7f9920436e5970a4413c46325ed03`.


## Final combined verification attempt — prompt 4 (2026-10-11)

Starting branch, worktree and HEAD were reverified:
`codex/store-backoffice-reconciliation-20261011-01`,
`/workspace/store-backoffice-reconciliation-20261011-01`,
`29e5e2527bf7f9920436e5970a4413c46325ed03`. All 20 reconstructed-audit
source/documentation changes were preserved and their actual diffs reviewed.
The source disposition and pending-module tables above remain authoritative;
no additional source branch, dependency or deferred activation was integrated.
The missing `aed3feab5b6edd95fb941ee92a455e058403def5` remains excluded.

### Current runtime blocker and completion gate

The current shell is uid 1000 (`agent`), in the socket's `agent` group; the
Docker socket is `srw-rw----`, owned by `nobody:agent`. Both explicit local
Docker health diagnostics and provisioned PHP-wrapper invocations fail with:
`dial unix /var/run/docker.sock: connect: operation not permitted`.
Host PHP/Composer are unavailable. Environment status reports running/connected
with enforced restricted networking, not Docker socket readiness. No daemon
restart, platform bypass, installer or alternate checkout was used.

The user explicitly authorized internet access during this prompt. Network and
narrow Docker-socket permission requests returned no granted permissions; a
subsequent socket diagnostic still failed. User authorization does not establish
that the sandbox applied access. Recover access to the existing local Docker
runtime before resuming runtime verification; no additional owner business
approval is needed for these already-authorized isolated checks.

Required runtime-equivalent attempts (all from this integration worktree):

```sh
/workspace/work/store-reconcile-runtime/bin/php vendor/bin/pint --parallel --test
/workspace/work/store-reconcile-runtime/bin/php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
/workspace/work/store-reconcile-runtime/bin/php artisan config:clear
/workspace/work/store-reconcile-runtime/bin/php artisan test --parallel --compact --processes=4
/workspace/work/store-reconcile-runtime/bin/php artisan wayfinder:generate --skip-routes --with-form
/workspace/work/store-reconcile-runtime/bin/php /runtime/audit/compile.php
PATH=/workspace/work/store-reconcile-runtime/bin:$PATH npm run build
```

Every PHP attempt exited 126 before PHP ran. Vite exited 1 at Wayfinder's PHP
invocation, with zero modules transformed. These are environment failures, not
application test failures or successful checks. No current full backend total
exists: zero backend tests executed in prompt 4. Prior overlapping focused
results are preserved as historical evidence and are not summed or substituted.
Fresh PHP formatting/static analysis, route regeneration, retained-template
compilation/rendering/PDF checks and public HTTP captures remain blocked.
The earlier build, 255-template syntax run, representative render/PDF samples
and captures were not repeated in this prompt and are not final verification.

### Frontend integration-test corrections and current checks

An actual full `npm run test:unit` run executed 2,050 tests in 227 files:
2,016 passed, 34 failed across 11 files. The failures demonstrated incompatible
independently developed tests after shell/shared-component integration:

- Full-mounted pages' first FormField or search input now belongs to the sidebar.
  Blog categories, colors, bulk upload, offers catalogue and customer tests now
  select the page field by its actual ID or native page form. Labels, errors,
  request/filter payloads and native validation assertions remain intact.
- The settings tests confused the shell's vendor cache-clear Form with settings
  writes. Their helper excludes only the exact selected `clearCache` action;
  it still full-mounts the shell, verifies exact settings/SMTP actions and refuses
  all page writes without their permissions. No permission assertion was removed.
- Exact report/notification Pagination prop expectations now include `async:false`;
  representative package AddResourceLink expectations include `inertia:true`.
  These preserve exact whole-prop comparisons rather than relaxing assertions.

Eleven additional test files changed: `resources/js/components/StoreReportPage.test.ts`
and tests under `resources/js/pages/Store/Admin/` for `Blog/Categories/Index`,
`Catalogue/Offers/Index`, `Customers/Customers/Customers`,
`Notifications/Customers/Index`, `Notifications/Index`, `Product/BulkUpload/Index`,
`Product/Colors/Index`, `Reports/Reports`, `Representatives/Representatives`,
and `Settings/Index`. No production frontend behavior was changed for these fixes.
Intermediate import ordering and helper union-type issues were fixed through
ESLint/TypeScript, not excluded from checks. No retries or weakened assertions.

Final actual full frontend rerun: `npm run test:unit` passed **2,050 tests in
227 files**, exit 0 (`final/test-unit-rerun.log`, 177.51 seconds). This is the
unique count from one complete run, not a sum of focused runs. Final
`npm run types:check` passed, exit 0 (`final/types-check-final.log`).
Full ESLint/Prettier results and final diff status are recorded in the last
handoff entry below. Expected jsdom "navigation to another Document" notices
were logged without test failures. No test was skipped or retry added.

### Outstanding findings classified from actual code

- **Inherited defects:** brands admin template still reads `order_level`, absent
  from both schemas; dashboard's product query still omits translations. Both
  templates are byte-identical to baseline `cdf719789541079ce86c5dc1b605520aa1ab7c9f`.
  `AppServiceProvider` strict-mode behavior is also unchanged and applies only
  in local/testing. Earlier populated-fixture failures establish rendering debt,
  not new migration regressions or permission to activate core catalogue/charts.
- **Inherited retained security/route concerns:** legacy uploader destruction,
  path and external-preview-link bodies match baseline. Its commented modal
  delete URL was already absent. Reviewed prior reflection artifacts contain 44
  remaining current missing URI/action references, all among baseline's 59;
  zero newly missing references. This is source/artifact review, not fresh route
  execution, authorization or demonstrated exploitability.
- **Environment limits:** isolated MySQL cannot start or be inspected through the
  denied Docker socket. Signed foreign keys, real row-lock behavior, collation,
  engine-specific migrations and transactional order replay/rollback are not
  proven against MySQL. SQLite historical results do not prove these properties.
  No production database was contacted and deployed grants remain unverified.
- **Permission static evidence:** six additive definition migrations use
  `insertOrIgnore` in the store guard after the section-column migration; no role
  pivot writes or destructive rollback. Existing customer/logistics/offers tests
  cover idempotency and preservation of pre-existing store/web grants. Coverage
  gap remains for dedicated database tests of
  `2026_10_10_035933_add_order_entry_permissions.php` and
  `2026_10_11_235900_define_store_blog_and_support_action_permissions.php`;
  the existing integration test covers blog/support no-auto-grant but not order
  definitions/rollback. Existing action tests cover refused writes/guard isolation,
  reserved keys, ownership/storage failures and established tax behavior; their
  full combined rerun remains blocked. Creating definitions grants no staff access.
- **Decision-dependent:** all owner decisions in the deferred-source table remain
  held, including editor/chart/map dependencies, scripts, seller/classified use,
  packages/core catalogue, global image/SVG policy and business/logout policies.
  No new privileges or dependency approval were inferred.

### Baseline compatibility, HTML/SEO and retained consumers

Baseline worktree still points to exact detached
`cdf719789541079ce86c5dc1b605520aa1ab7c9f`. Manifests and Composer lock match;
current manifests/locks also have no diff against integration's develop base.
Baseline frontend lock differs in 14 package versions: Babel parser
`7.29.8→7.29.9`; Vue and nine Vue packages `3.5.41→3.5.43`; nanoid
`3.3.18→3.3.20`; postcss `8.5.26→8.5.29`; source-map-js `1.2.1→1.2.2`.
Installed versions match current lock. Recorded local semver inspection confirms all seven dependency edges from
non-upgraded parent packages and all 37 upgraded-package installed edges
satisfy their ranges; the Vue family is coherently upgraded together. Historical
Vue internal exact pins differ: this is compatible substitution, not historical
lock equivalence. Non-version lock changes are root name and devOptional flags
for `@types/node`/`undici-types`. No packages installed or locks rewritten.

The prior initial-HTML captures and comparison were reviewed: ten samples preserve
status/redirect, primary text, links and metadata; CMS improves 500→200. No setup
correction affecting captures was made. CSRF and operational uploader metadata
remain excluded from equivalence. Fresh captures cannot run in this environment.
Homepage listing/search `og:url`, missing `/temp` in CMS/policy `og:url` and
indexable login are inherited gaps. Canonical/JSON-LD/itemscope features absent in
both successful sampled trees remain absent; product itemprop metadata is preserved.

A fresh source recount finds 255 Blade templates (253 shop, 73 backend). Guide
§5.1 exactly lists all 73 retained backend files. Literal `@extends` consumers:
backend admin layout 49; backend auth/error layout 3; frontend layout 49;
frontend user panel 19; seller layout 29. These are physical source consumers,
not unique endpoints or runtime render totals. PDF/download layouts, seller,
authentication, emails, admin includes, assets, Inertia root and Blade CSP remain.
No shells or exclusions were removed in prompt 4.

### Browser attempt and next verification

Installed Chromium `151.0.7922.173` was checked; no browser download/install.
The established HTTPS/enforced-CSP configuration was attempted for all store
spec files with:

```sh
PATH=/workspace/work/store-reconcile-runtime/audit/bin:$PATH PLAYWRIGHT_BROWSERS_PATH=/workspace/work/store-reconcile-runtime/audit/browser-cache PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/usr/bin/chromium PLAYWRIGHT_BASE_URL=https://127.0.0.1:9443 TRUSTED_PROXIES=127.0.0.1 npx playwright test tests/e2e/store- --output=/workspace/work/store-reconcile-runtime/final/browser-results
```

The configured web server exited 126 at its first Docker PHP invocation; zero
browser tests executed. The HTTPS proxy was not started because the PHP upstream
could not start. Settings, representatives, notifications/profile, orders and
remaining broader workflows therefore remain unverified in prompt 4. Prompt 3's
two seven-test runs retain only their stated narrower historical coverage.

Runtime recovery must precede full backend/check/build, broader Chromium,
retained rendering/PDF and isolated MySQL verification. Keep deterministic isolated
databases/storage, local HTTPS/enforced CSP and zero retries. Do not infer a
successful full suite from discovery, historical traces or source review.
Logs for this attempt are outside the repository at
`/workspace/work/store-reconcile-runtime/final/`. No scratch/generated assets,
credentials or database files are source changes. No local commit or staging was
performed because required combined verification is substantively blocked.
All 31 source/documentation changes remain reviewable; HEAD is unchanged.
Publication readiness is not established. No push, deployment or live transaction.


### Final prompt 4 handoff state

- Full Vitest: **227 files / 2,050 tests passed**, one complete rerun.
- Full `npm run lint:check`: passed, exit 0 (`final/lint-check-last.log`).
- Full `npm run format:check`: passed, exit 0 (`final/format-check-final.log`).
- Full `npm run types:check`: passed, exit 0 (`final/types-check-final.log`),
  using existing generated actions; fresh Wayfinder generation is blocked.
- Final settings helper/permission assertions: 20/20 passed after lint/type
  corrections (`final/settings-last.log`); this is additional focused evidence,
  not added to the full-run total.
- `git diff --check`: passed. No staged files; 28 modified and 3 untracked intended
  source/test/documentation files. All original 20 audit changes remain preserved.
- Baseline semver evidence is saved in `final/baseline-dependencies.json`:
  14 changed installed versions, seven non-upgraded-parent edges and 37 current
  upgraded-package edges; every inspected range satisfies the installed version.
- Backend, PHP/PDF/template checks, fresh route generation and production build
  remain environment-blocked. All-store browser attempt executed zero tests;
  isolated MySQL/deployed grants remain unverified. No current backend unique
  test total or final runtime verification claimed.
- No local commit was created because the required gate is incomplete. Final
  HEAD remains `29e5e2527bf7f9920436e5970a4413c46325ed03`; branch/worktree unchanged.
  Restore Docker access and resume the blocked checks before staging/committing.


### Runtime-access follow-up (2026-10-11)

Reverified the same integration branch/HEAD and all 31 uncommitted files. No
completed frontend checks were repeated; no application/test code changed.
Current managed-environment status reports running/connected, revision 21,
enforced restricted HTTP policy and no configured VPN/TCP destination grants.
That status does not establish socket or execution readiness.

Following the managed Docker guidance, endpoint/context/TLS selectors were
cleared for the explicit `unix:///var/run/docker.sock` health diagnostic.
`dockerd` is running (observed PID 200); task uid/gid are 1000 (`agent`), and
socket mode/owner/group are `srw-rw---- nobody:agent`. Ordinary group permissions
allow access. `/var/run/docker.sock` resolves to `/run/docker.sock`.
The connection returns `EPERM`, not absent daemon/socket or ordinary mode denial.
A Python AF_UNIX probe creates the socket successfully and its connect returns
errno 1 / `EPERM`. `/proc/self/status` reports `NoNewPrivs:1`, `Seccomp:2` and one
filter. This is consistent with an execution/sandbox restriction, but does not
prove which filter/rule enforces it. `strace` cannot narrow the cause because
`PTRACE_TRACEME`/`PTRACE_SETOPTIONS` are also denied with operation not permitted.

A specific permission request covered read/write connection access to
`/run/docker.sock` plus network execution needed for runtime services. It returned
no granted permissions (`network:null`, `file_system:null`). A subsequent explicit
health diagnostic still failed. No socket chmod/chown, daemon restart, alternate
privileged socket, container extraction, proxy bypass or sandbox bypass attempted.
The running daemon should be retained; the managed executor must restore the
supported ability to connect to the local Docker socket. Required downstream
browser/MySQL checks also need supported local TCP listening/connection access.

Supported-local-runtime assessment:

- `php`, `php8.5`, Composer, mysqld and mariadbd executables are absent from PATH;
  conventional PHP locations and scoped runtime/tool searches find only the
  existing Docker wrappers. The external Composer PHAR needs a PHP interpreter.
- `bash .claude/scripts/install_php.sh --check` exits 1. Its installer requires
  root, writes an Ubuntu Ondrej PPA using the OS codename and installs PHP 8.5
  extensions. Current OS is non-root Debian 13/trixie; running that installer
  unchanged is not a supported fallback. No Claude hooks were invoked.
- gcc/make/pkg-config exist, but development metadata is absent for libxml,
  SQLite, libzip, ICU, PNG/JPEG/WebP and oniguruma. zlib/curl/OpenSSL metadata is
  present. An ad-hoc incomplete PHP build would not meet the repository's PHP
  8.5/extension requirements or establish platform compatibility.
- A single permitted-destination HTTPS probe through the inherited configured
  proxy to `api.github.com` exits 7: failed to connect to proxy port 8080.
  No HTTP response or destination-policy rejection is observed; do not claim
  this was a GitHub allowlist failure. Downloading/provisioning an official
  runtime or missing build prerequisites is blocked by current connectivity.
  No dependency, system package, manifest or lockfile was changed.

Independent source check confirms phpunit.xml uses testing/SQLite `:memory:`,
empty DB_URL, isolated ancillary in-memory connections, array mail/cache/session,
and disabled telemetry. This is setup inspection, not a backend test result.
`git diff --check` passes. The previously completed full frontend run and checks
remain valid because only documentation changed during this follow-up.

Diagnostics/exit codes and preserved-file fingerprints are external under
`final/runtime-access/` and `final/runtime-access-preserved-files.json`.
Backend/Pint/PHPStan/Wayfinder/build/template-render/PDF/browser and isolated
MySQL checks remain outstanding; no new runtime result is claimed. Deployed
role grants remain explicitly unverified. No staging or local commit because
verification is still unsatisfactory; all 31 files and starting HEAD remain.
Restore managed execution/socket connectivity, then resume the exact outstanding
commands and isolated harness recorded above. No push, deployment or live calls.


### Authorized local checkpoint

The user explicitly authorized a preservation-only checkpoint with message
`chore: checkpoint reconciliation pending backend verification`. It contains
exactly the 31 intended integration/audit/test/documentation files; scratch,
cache, credentials and generated artifacts are excluded. This authorization
supersedes the earlier decision to leave the blocked work uncommitted, without
changing verification or publication status. Backend/PHP/build/template/PDF,
browser and isolated MySQL checks remain outstanding; deployed grants remain
unverified. Frontend results remain as recorded. No push or deployment.
A verified Git bundle will preserve the integration branch's reachable history.


## Resumed runtime verification (2026-10-11)

Fetched and checked out the integration branch in the separate worktree above;
starting HEAD was exactly `924d7d9319cd1a69c41e0b191724a309f05ae86a`, the authorized
preservation checkpoint. The subsequent resume preserved every local fix and
confirmed the same HEAD. The original checkout remains clean. Current scratch,
runtime helpers, logs and browser traces are external under
`/workspace/work/store-reconcile-resume/`; none is a source change or a commit
candidate. Untracked `public/uploads/` contains browser-generated files and is
excluded from the intended source changes.

### Runtime recovery and completed checks

Checked PHP/Docker execution before setup. Supported network-enabled execution
restored Git fetch and local Docker socket access during the preceding resume.
The provisioned PHP 8.5.11 runtime initially lacked GD WebP; the five image-test
failures in `logs/backend-initial.log` were runtime limitations. An external
Docker image enabled WebP with the configured proxy/CA trust; the resulting GD
extension is selected by the external activation script. No platform-requirement
bypass, TLS bypass, system/root installer, dependency installation or lockfile
change was used. `composer check-platform-reqs` passed with the existing lock.
Dependencies were copied into this worktree from the provisioned checkout.

Results below were actually executed in the preceding resume and are reused.
The latest changes are browser-test spacing and documentation, so they do not
invalidate backend, build, template or frontend application-test results.

| Check | Actual result | External evidence relative to the scratch directory |
| --- | --- | --- |
| Full backend suite | **4,961/4,961 tests passed; 23,810 assertions**, including the four new permission-migration tests | `logs/backend-final.log` |
| Affected SQLite order tests after MySQL fixture corrections | **73/73 passed; 431 assertions**; this is subsequent focused evidence, not another full-suite run | `logs/sqlite-order-final.log` |
| Pint | Full `--parallel --test` and dirty/scoped checks passed after import-format fixes | `logs/pint-final.log`, `logs/pint-dirty.log` |
| PHPStan | Passed with zero errors after the PHP test changes | `logs/phpstan-final.log` |
| Fresh Wayfinder | `php artisan wayfinder:generate --skip-routes --with-form` passed | `logs/wayfinder.log` |
| Production assets | `npm run build` passed; fontaine/chunk-size advisories remain | `logs/build.log` |
| TypeScript after fresh actions | `npm run types:check` passed; fresh generation justified this repeat | `logs/types-fresh-actions.log` |
| Blade syntax | All **255** current/retained templates compiled and generated PHP linted with zero failures | `logs/templates.log`, `logs/templates.json` |
| Retained rendering/PDF | Full backend coverage includes nullable CMS metadata, configured VoguePay currency, seller/email/reference template rendering, real invoice PDFs with `%PDF-` signatures, and representative receipt coverage | Full backend log and the existing render/PDF tests |
| Isolated MySQL | Fresh migrations passed; **101/101 focused tests passed; 587 assertions** on MySQL 8.4.11 | `logs/mysql-migrate.log`, `logs/mysql-tests-final.log` |
| MySQL engine/locking/FK probe | All stated checks below passed | `logs/mysql-engine.json` |
| Changed browser-spec lint/format, latest resume | Scoped ESLint and Prettier checks passed after two required blank lines | `logs/e2e-lint-final.log`, `logs/e2e-format-final.log` |

The previous full frontend **2,050 tests / 227 files**, full ESLint and full
Prettier results remain as recorded in prompt 4. They were not repeated because
no production Vue/TypeScript change invalidated them. Manifests and lockfiles
remain identical to the recorded develop baseline. Template syntax and sampled
rendering are distinct from visual PDF review and complete endpoint coverage;
the admin receipt download unit test uses a mock, while retained PDF tests supply
runtime rendering evidence. No live payment or email delivery was exercised.

### Local fixes and isolated MySQL evidence

Pint corrected imports in `HandleInertiaRequests`, `Category` and
`RouteServiceProviderTest`, without a behavior change. New
`AddStoreActionPermissionsTest` covers the order-entry and blog/support
migrations: store-only definitions, existing staff denied without grants,
idempotent replay, and nondestructive rollback preserving store/web permissions
and role pivots. Its four tests and 31 assertions are included in the full suite.
This closes the dedicated test gap recorded in §8 of the migration rules; it
does not verify or authorize any deployed role grants.

MySQL exposed two SQLite-permissive test assumptions. The order test now compares
decoded JSON content instead of insignificant JSON spacing, also checking the
persisted shipping cost. The account-search fixture uses a valid integer
`AccSysID` rather than `MATCH`. Both affected SQLite and isolated MySQL suites
passed afterward; order tax/grand-total business behavior was preserved.

The isolated MySQL suite covers order migrations, the six additive permission
migrations, admin orders and bounded product search. The direct two-connection
probe confirms InnoDB/`utf8mb4_unicode_ci` for `orders`, `order_details`,
`product_stocks`, `wms_permissions` and `wms_store_order_submissions`; all eight
actual product foreign-key columns match their signed `int` parent. A held
`FOR UPDATE` row rejects a second connection's `NOWAIT` with error 3572, rollback
releases the lock, transactional payload-hash rollback preserves the old hash,
and the collation is case insensitive. Its temporary probe row was deleted.
This is isolated engine evidence, not a production-data migration rehearsal or
an end-to-end concurrent HTTP load test. The session-owned MySQL container was
stopped after these checks (`logs/mysql-stop.log`).

### Browser results and current execution limitation

Used installed Chromium 151 with local HTTPS, enforced CSP, one worker, zero
retries and no server reuse. Each run resets only the four dedicated e2e SQLite
files through the existing Playwright configuration. Prior actual attempts:

| Attempt | Actual result | Evidence |
| --- | --- | --- |
| All store specs, before contract fixes | **31 passed, 8 failed** (39 tests) | `logs/browser-initial.log` |
| Seven affected spec files after initial fixes | **11 passed, 3 failed** (14 tests) | `logs/browser-focused.log` |
| Language/orders/support follow-up | **5 passed, 2 failed** (7 tests) | `logs/browser-fix2.log` |
| Latest final all-store attempt | **Zero tests executed**, web-server startup exit 1; loopback connection denied with `EPERM` | `logs/browser-final.log`, `logs/browser-final-startup.log` |

The browser fixes align selectors with the canonical uploader, current Inertia
visits and retained Blade trips, escaped product-search buttons, explicit total
labels, and the accessible header picker/focus return. The simulated failed
support reply uses the request's HTTPS origin and a final Inertia error response;
its text-retention/dark-mode assertions passed in the follow-up. It does not
claim an actual failed-write redirect was tested. The Arabic fixture is
idempotent; each RTL save now waits for its response and reloads to assert
persistence. The final follow-up showed the endpoint returns 302, whereas the
test expected 303 and stopped after saving RTL off; the dependent order RTL
assertion then failed. The latest 302 expectation is preserved but remains
browser-unverified until the all-store suite can execute. Partial runs are not
combined into a claimed 39-test pass.

The latest managed shell supports PHP/filesystem commands but denies loopback
TCP bind/connect. An additional-network execution request was rejected by the
current granular approval policy; the supported network permission request
returned no grant. Default execution confirmed Playwright's
`connect EPERM 127.0.0.1:9443` and startup failure. No permission or sandbox bypass
was attempted. This differs from the earlier out-of-credits automatic-review
failure, which blocked the preceding final attempt before execution. Supported
local network execution must be restored before the remaining browser gate.

Retained dashboard lazy-loading can still return 500 after login, legacy vendor
JavaScript emits a null-`getAttribute` error, and Google Fonts is unavailable
under the current network policy. These are recorded limitations; browser
workflow assertions are not evidence of globally clean console/network output.
Previous traces/screenshots remain external for review.

To resume after network access is restored, start the external session-owned
HTTPS helper (it was stopped during cleanup), then run from this worktree:

```sh
source /workspace/work/store-reconcile-resume/activate.sh
PLAYWRIGHT_BROWSERS_PATH=/workspace/work/store-reconcile-resume/browser-cache PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/usr/bin/chromium PLAYWRIGHT_BASE_URL=https://127.0.0.1:9443 TRUSTED_PROXIES=127.0.0.1 npx playwright test tests/e2e/store- --output=/workspace/work/store-reconcile-resume/browser-final
```

### Remaining gate and preserved scope

All deferred source dispositions and owner decisions above remain unchanged:
Tiptap/editor, chart/Leaflet dependencies, stored-script policy, seller/classified
feature use, pending catalogue/customer-package work, SVG/shared upload policy,
departure semantics, order tax arithmetic, logout and server status. The missing
audit commit remains excluded and unrecovered. Deployed role grants, production
MySQL version/configuration and data rehearsal, external services/payment/email,
and unavailable baseline/visual captures remain unverified; no production access
or deployment occurred.

Only session-owned helper processes are cleanup targets; the healthy managed
Docker daemon and unrelated services are retained. The owned HTTPS proxy was
stopped; Playwright's attempted upstream exited. Source fixes and generated
artifacts are preserved. Verification-record file links and `git diff --check`
pass. The combined gate is still incomplete because the latest browser changes
have not run. No staging or new local commit; HEAD stays at the checkpoint above.
After a successful fresh all-store run, update both records, clean up its helpers
and create the authorized local verification commit. No push, PR, merge or deploy.


### Authorized backup checkpoint — pending browser verification

The user subsequently authorized a preservation-only local checkpoint and its
publication to the public `arb-rajab/warehouse_management` repository on a new
backup branch. This supersedes the previous no-commit/no-push instruction only
for this checkpoint. Intended branch:
`codex/store-backoffice-reconciliation-pending-browser-backup-20261011-01`.
Commit label: `chore: checkpoint reconciliation fixes pending browser verification`.

The checkpoint contains exactly the intended PHP formatting/test fixes, seven
browser-spec fixes, new action-permission migration tests and these two records.
Scratch, runtime helpers, generated assets/actions, database files, uploads,
logs and secrets are excluded. Completed check results are reused; the full
pre-push verification script is not repeated for this preservation checkpoint.
The latest browser gate and deployed-role/production verification limits remain
unresolved. This checkpoint does not claim verification or publication readiness.
The backup push must create only the new branch and verify its remote HEAD;
`develop` and the existing integration branch are not push targets. No PR, merge
or deployment is authorized. Push/remote verification outcome is reported to the
user after execution, rather than presumed by this authorization record.


## Final all-store browser verification (2026-10-12)

Fetched and checked out
`codex/store-backoffice-reconciliation-pending-browser-backup-20261011-01`
in `/workspace/warehouse_management`. Starting HEAD was confirmed exactly as
`a0c880ea376e716ee633c5c5191c45a0a521780a`. Both verification records and the
repository guidance were read before verification. This section supersedes the
pending browser gate above; prior attempts retain their historical scope.

### Runtime and isolation

Before starting servers, IPv4 `127.0.0.1` and IPv6 `::1` bind/connect/data-transfer
probes passed through approved execution. Default sandbox execution still denies
sockets; approved execution supported the actual servers and browser runs.
Used the provisioned native PHP 8.5.11 runtime and installed Chromium
151.0.7922.173, with Playwright 1.62.1. The native GD extension initially lacked
WebP. An external helper image rebuilt that extension with WebP using the
configured proxy and mounted CA trust; native WebP encoding and Composer platform
requirements passed. No application dependency or lockfile changed.

Fresh Wayfinder generation through `npm run build`, production assets and
`npm run types:check` passed. The stale provisioned Vite hot marker was preserved
externally so browser requests used those production assets. Build advisories
for optional fontaine and chunk size remain. Generated assets/actions, the local
certificate/key, runtime extensions and logs are excluded from source changes.

Every browser invocation recreated only the four dedicated `database/e2e*.sqlite`
files through the checked-in configuration, including ancillary connections and
existing store seeders. The resolved database paths were checked before execution.
Mail used the array driver. All runs used local HTTPS, enforced CSP, one worker,
zero retries and `reuseExistingServer:false`.

### Actual browser results

Current external evidence is under
`/workspace/work/store-browser-final-20261012/`; paths in this table are relative
to that directory. Counts describe individual runs and are not summed.

| Run | Result | Evidence |
| --- | --- | --- |
| First all-store run, Python HTTPS helper | 37 passed / 2 failed | `logs/browser-final.log`, `browser-final/` |
| Unchanged driver/support files, fresh databases | 3/3 passed | `logs/browser-affected-initial.log` |
| Second all-store run, Python HTTPS helper | 38 passed / 1 failed | `logs/browser-final-rerun.log`, `browser-final-rerun/` |
| Driver/marketing/support files, Node HTTPS helper | 5/5 passed | `logs/browser-transport-focused.log` |
| **Final complete all-store run, Node HTTPS helper** | **39/39 passed in 22 files; exit 0 (2.7 minutes)** | `logs/browser-final-node.log`, `browser-final-node/` |

Final command, with the session-owned Node HTTPS helper already listening:

```sh
source /workspace/work/store-browser-final-20261012/activate.sh
npx playwright test tests/e2e/store- --output=/workspace/work/store-browser-final-20261012/browser-final-node
```

The first driver failure was `net::ERR_TOO_MANY_RETRIES` during navigation. The
support RTL and later coupon dark-control failures each have a trace showing
the application stylesheet request failed with status -1; their CSS assertions
were running without that stylesheet. The Python helper also logged broken-pipe
and SSL write errors. Replacing this session-owned helper with a streaming Node
HTTPS proxy that handles cancelled requests resolved the affected checks and
the complete suite. Application and browser-test source were unchanged; no
assertion was weakened and no retry was added. Failed traces remain available.

The final run verifies the latest RTL correction: both saves receive 302, reloads
confirm persisted off/on values, and the subsequent Arabic order-layout check
passes. It also includes the existing coexistence, native confirmations, picker
focus, dark controls, upload/profile, reports, representatives, catalogue,
marketing, logistics, support, orders and header workflows. Simulated support
errors retain their previously documented coverage limit.

### Reused checks, scope and cleanup

The recorded full backend **4,961 tests / 23,810 assertions**, full frontend
**2,050 tests / 227 files**, isolated MySQL **101 tests / 587 assertions** and
engine probe, PHP analysis/formatting, template/render/PDF and earlier lint/format
results are reused rather than claimed as newly executed. No application/test
source changed to invalidate them; fresh generated actions justified the current
TypeScript check. Their original logs belong to the earlier workspace. Current
manifests/locks match the develop baseline and recorded source fingerprints.

Playwright stopped its upstream servers after each run. Both session-owned HTTPS
helpers were stopped; ports 8010 and 9443 have no listeners, and no helper
container remains running. The managed Docker daemon was retained. Generated
public uploads were preserved externally, leaving only these two verification
records as commit candidates. Relative file links and `git diff --check` pass.

The browser gate is satisfactory for the authorized local verification commit.
All deferred source dispositions, inherited retained-module issues and owner
decisions remain unchanged. Production MySQL/data rehearsal, deployed role
grants, external services/payment/email, complete visual parity and unavailable
baseline captures remain outside the completed evidence. Workflow success does
not claim globally clean console/network output. No push, PR, merge or deployment
was performed or authorized in this session.


## CI repair on 2026-10-12

PR #377's first GitHub run passed Pest, MySQL migrations, Vitest, lint,
formatting and static analysis, but failed dependency audit and browser tests.
The browser harness used HTTP while enforced CSP upgrades Inertia redirects to
HTTPS. A local trace confirmed a successful login followed by a failed HTTPS
redirect to the HTTP-only server.

The default harness now starts a loopback HTTPS proxy with a temporary OpenSSL
certificate, trusts only that proxy and stops its owned PHP process group on
shutdown. Upstream connections are not pooled against PHP's development server.
CSP and the existing assertion thresholds remain enforced. The arrow contrast
test uses a CPU readback hint and polling for the rendered color measurement;
both arrow assertions passed three repeated runs. Locale-switch callers now
wait for the full reload before subsequent navigation; pagination contrast
checks also wait for their rendered measurement without lowering thresholds.

Security maintenance updates existing dependencies: `enshrined/svg-sanitize`
0.22.0 to 1.0.0 resolves the three reported SVG advisories; `concurrently` 9.2.4
to 9.2.5 selects fixed `shell-quote` 1.12.0 within the existing manifest range.
No unrelated framework, editor or chart dependencies were activated.

Local verification: the required pre-push script passed all 4,961 backend tests
(23,811 assertions), all 2,050 frontend tests in 227 files, ESLint, Prettier,
TypeScript, Pint and PHPStan. The fresh production build passed. Focused upload
coverage passed 110 tests/492 assertions with WebP-enabled GD. Production npm
audit reported zero vulnerabilities. Composer update reported no advisories;
the standalone audit endpoint is blocked by local network policy and must be
verified by GitHub. The full development npm audit still reports inherited
`braces`/`postcss-selector-parser` tooling advisories outside the production
audit gate; this repair does not claim to resolve those.

Final full-browser result: **59/59 passed**, fresh isolated databases, one
worker, zero suite retries, local HTTPS and enforced CSP. Local video capture is disabled only in an
external scratch config because Playwright's ffmpeg binary is unavailable;
screenshots and traces remain enabled, and committed CI video settings remain.

These fixes do not complete deferred modules or resolve owner decisions,
production-data rehearsal, deployed role grants or inherited product defects.


## Carrier administration migration (2026-10-12)

User-authorized carrier-only implementation on `codex/carrier-admin-inertia`,
created after fetching latest develop `018dd1802c0b2fc99ffbf0e097594fe0f0f3dfef`
(the merge of PR #377). The prior audit branch and existing work were preserved;
carriers were confirmed unmigrated before changes. This follow-up authorizes a
local commit only, not a push, PR, merge or deployment.

### Implemented scope and preserved contracts

- `routes/shop-admin.php` now registers the seven implemented carrier actions
  explicitly, retaining names and the existing `manage_carriers` gate on every
  action. The unused/broken resource `show` and duplicate destroy registration
  are removed; `LegacyAdminPermissionsTest` drops only that obsolete exception.
- Replaced root `CarrierController` / `CarrierRequest` with
  `Store/Admin/CarrierController`, dedicated filter/store/update/status requests,
  `Concerns/ValidatesStoreCarrier` and `Store/CarrierResource`. Only the three
  carrier Blade screens are deleted. Narrow list payloads page/filter normally;
  edit payloads retain range order, billing types and zone-keyed prices.
- Nested list/matrix validation checks existing zones, aligned prices/bounds,
  contiguous increasing ranges and decimal schema widths. Persisted range
  boundaries remain lower-inclusive/upper-exclusive. Create, replace-all update
  and delete are transactional; update/delete lock the carrier. Tests inject
  mid-write/delete failure and prove the complete prior tariff survives.
- `Carrier`, `CarrierRange`, `CarrierRangePrice` are typed and removed from both
  analysis exclusion lists. The price-to-range relation explicitly selects the
  actual `carrier_range_id`; relationship tests include unrelated records.
  Separate API/model exclusions are retained unless cleaned here.
- `StoreCarrierFormFields` and the three `Store/Admin/Setup/Carriers` pages reuse
  canonical `StoreUploadField`, StoreAdminLayout, ResourceFormPage, DataTable,
  Pagination and StoreStatusButton. English/Arabic labels and exact decimal
  validation use existing dependencies. New logos obey canonical library/owner
  authorization; unchanged legacy logos round-trip without authorizing foreign
  replacement or exposing unauthorized previews.
- Menu/shipping configuration now navigate to carriers through Inertia. The
  reference browser test expects this. Staff coexistence and legacy-uploader
  tests instead use retained general settings, with the latter scoped to its
  `system_logo_white` picker, preview and removal control.
- Shared `Helpers.php` and `Api/V2/CarrierController` and public/API carrier
  registrations are unchanged. Tariff compatibility tests exercise existing
  weight/price billing and both zones at lower/upper boundaries. The retained
  helper lazily traverses relations, so those calculation tests explicitly use
  production-style lazy-loading and restore the strict-mode flag afterward;
  no helper behavior or global application strict-mode policy was changed.

### Verification and runtime evidence

Native PHP 8.5.11 and existing installed dependencies were used. No installer or
Claude SessionStart hook was assumed to run. The available GD lacked WebP;
a disposable Docker runtime built the same-version GD extension with WebP,
using the managed proxy and read-only CA mount, and exported it with required
libraries into the external local runtime. Native WebP availability and all
upload tests then passed. Application manifests/locks are unchanged.

- Required `python3 .claude/scripts/pre_push_check.py`: exit 0. ESLint,
  Prettier, TypeScript, Pint and PHPStan passed; PHPStan reported zero errors.
  Full Vitest: **2,069 tests in 231 files**. Full parallel Pest: **5,035 tests,
  24,375 assertions**. No required-script step was skipped.
- Focused carrier/controller/model/tariff/permission/exclusion/state-changing
  checks: **89 tests passed**. Separate retained shell/CSP tests: **8 passed,
  51 assertions**. Focused carrier/navigation Vue run: **35 passed in 6 files**.
- Fresh `npm run build`: passed, including Wayfinder generation. The existing
  large-chunk advisory remains a build warning, not a failed build.
- HTTPS Playwright: **6/6 passed in four files**, Chromium via the installed
  system executable, one worker, zero retries, enforced CSP and no server reuse.
  Dedicated e2e databases and the committed HTTPS harness were used; system
  ffmpeg was copied only into the workspace browser cache, so video, screenshot
  and trace settings remained enabled. Coverage includes tariff round-tripping,
  canonical logo selection, native delete confirmations, Arabic/RTL/native
  validation/dark contrast, legacy uploader and mixed Blade/Inertia navigation.
- Initial browser attempt failed because an existing Vite `public/hot` file
  selected HTTP development assets blocked by enforced HTTPS CSP. Traces proved
  this environment cause. The rerun temporarily hid that runtime file to use
  the fresh production assets and restored it afterward. No CSP relaxation,
  retry or assertion reduction was introduced. Owned browser servers stopped.
- `git diff --check` and documentation link/inventory checks passed. Scratch
  logs, runtime helpers and generated uploads are preserved externally under
  `/workspace/work/carrier-admin-inertia-20261012/` and excluded from the commit.

### Updated remaining inventory and boundaries

Actual `/temp` route inventory is **1,053** registrations; **441** start with
`/temp/admin`. Carrier admin has seven implemented actions. These counts are
registrations, not migrated-module counts.

Backend Blade files fell from **73 to 70**: blog 2, customer 4, dashboard 1,
downloads 3, includes 2, invoice 1, layouts 3, newsletter 1, product 15,
representative maps 3, sellers 13, setup configurations 2, support 1,
server status 1, trips 13, website settings 5. The migration guide §5.1 is
updated to the exact physical inventory.

A direct literal inheritance/include scan finds **46** templates extending
`shop.backend.layouts.app`, **3** extending `shop.backend.layouts.layout`
(store error 503, deliveryboy login, seller login), and one include each for
admin nav/sidenav. The blank layout has zero direct literal callers and remains
protected by the retained-shell test contract. These counts describe direct
Blade references, not every dynamic/runtime rendering path.

Root/seller invoice controllers and migrated bulk-export controllers still
consume invoice/download templates. `/temp/reviews` still consumes backend
reviews; seller product controllers still consume backend SKU partials. Shared
layouts/PDFs, legacy upload endpoints/assets, coexistence middleware and Blade
CSP are preserved. General settings/map settings remain Blade. Customer packages,
core catalogue, editor/chart/map activation, seller/classified feature usage and
all recorded owner decisions remain in their previous dispositions. No role
grants, dependencies, shipping calculations or brands/dashboard defect fixes
were added. Production data/grants and external services were not exercised;
this patch does not require them for its authorized local migration/commit.


## Customer package administration migration (2026-10-12)

User-authorized local-only follow-up on `codex/customer-package-admin` from
fetched develop `71de5cad28d33561ed832acc80d02cc822191e87`, the merge of PR #378.
GitHub was checked read-only: all 14 PR-head checks passed; merge SAST succeeded;
deploy run `38240427292` and deployment `7012504913` succeeded for `develop`.
The merge tree and PR head tree both equal
`070e6a5634cef666120c0844f5fc2f2ff804433c`. The existing-tests gate passed, so
`Test the merged commit` was skipped by that workflow; no new deployment was
requested. Existing deployment status is evidence, not authorization to deploy.

### Prerequisite and consumer findings

All three admin package screens were still Blade. The pending-package entries
in the integrated inventory describe implementation gaps; the deferred-source
prerequisite table contains no package-specific approval/dependency hold. Its
classified feature-use hold targets `CustomerProductController` and proposed
`ClassifiedProductController` activation. This request expressly authorizes
customer-package administration while preserving the classified-product route,
feature flag, public/seller/mobile contracts and pricing/benefits. No dependency,
role grant, global upload policy or business-policy approval was inferred.

Before removal, references to the package route names, root controller/model,
three Blade names, uploads and payment helpers were traced through application,
routes, resources and tests. The only direct consumers of the three deleted
admin views were the replaced root admin actions. The root controller still has
the customer purchase route; its two purchase method bodies are byte-identical
to develop and it remains in both legacy analysis exclusion lists. HomeController,
public customer package/product/dashboard views, VoguePay, Helpers.php,
GatewayPaymentService, payment adapters, StoreUser relations and V2 controller/
resource/route registrations retain their established contracts. Shared uploader
assets, seller/public/auth/email shells, PDFs and Blade CSP remain in use.

### Completed scope

- Six implemented admin route names preserved; bound models, canonical DELETE,
  PUT/PATCH updates and explicit redirects. Removed only empty/unused show and
  duplicate registrations; public `customer_packages.purchase` remains root.
- Preserved view/edit/delete gates. Create/store remain `edit_classified_package`
  gated. The new Add button follows that route gate; the old Blade Add-only gate
  never authorized its caller to create. No permission vocabulary/grants changed.
- Inertia list/create/edit under `Store/Admin/Customers/Packages`, shared layout,
  form/table/pagination/actions, StoreLanguageTabs, StoreUploadField, Wayfinder,
  English/Arabic copy and sidebar Inertia navigation.
- Narrow paginated records and scoped translations; schema-aware validation for
  names, amounts, product-upload benefits, languages and canonical image
  references. Nondefault translation updates preserve the base name and update
  shared fields, matching the retained behavior. Existing valid foreign logos
  may round-trip; no foreign replacement or unauthorized preview is granted.
- Atomic package/translation create/update/delete; locking on update/delete.
  Deletion retains existing customer assignments, benefit balances and payment
  history, including legacy dangling IDs. No purchase/payment arithmetic changed.
- Typed CustomerPackage and CustomerPackageTranslation, with dedicated relation/
  translation tests and both analysis exclusions removed. Legacy root, API and
  CustomerPackagePayment exclusions remain. No new casts change legacy payloads.

Current inventory: **67** backend Blade files (70 before this follow-up), **43**
literal inheritance/include consumers of `shop.backend.layouts.app`, **3** of
`shop.backend.layouts.layout`, **0** direct literal consumers of the blank shell.
There are **1,050** `/temp` registrations and **438** `/temp/admin` registrations.
Only `customer/classified_products/index.blade.php` remains in backend/customer.
The guide's current inventory and controller table are updated; earlier dated
carrier/reconciliation counts remain historical evidence.

### Verification runtime and limits

Existing vendor/node_modules were used without installs or lockfile changes.
PHP/Composer were absent from PATH, so the already-provisioned PHP 8.5.11 Docker
image and Composer image were exported to `/workspace/work/package-runtime`.
GD WebP support was built in an isolated runtime image using the managed proxy
and a read-only CA mount; application dependency manifests were untouched.
Platform checks passed. Wayfinder needed a 1 GiB runtime memory limit. Parallel
Pint initially could not bind loopback under restricted execution; network-enabled
execution of the same command passed. A stale `public/hot` marker pointed at an
absent Vite server and was preserved outside the checkout so production assets
could be tested with strict CSP. No active Vite process was found.

Focused backend/controller/model/permission/exclusion/purchase/payment/shell
checks: **167 passed, 749 assertions**. Focused frontend package/form/menu checks:
**21 passed in three files**. Production build passed with Wayfinder generation;
existing chunk-size and optional font-fallback advisories remain. HTTPS Chromium
browser checks: **5/5 passed**, one worker, zero retries, including package CRUD,
canonical raster upload/logo selection, shared price/benefit round trips,
English/Arabic translation tabs, sidebar Inertia visits, native delete
cancel/accept, Arabic RTL, dark contrast, native validation, authoritative error
round trips with draft retention and no persisted refused write. Retained legacy
uploader and mixed Blade/Inertia staff navigation also passed. Video capture was
disabled only in an external local config because the bundled FFmpeg was absent;
screenshots/traces and committed CI video settings remain. Existing dashboard
lazy-load failures and third-party/Permissions-Policy console warnings do not
establish a globally clean browser console and remain outside package scope.

The required `python3 .claude/scripts/pre_push_check.py` completed with exit 0
under supported network-enabled execution: ESLint, Prettier, TypeScript, Pint,
PHPStan (zero errors), **2,079 Vitest tests in 233 files** and **5,102 Pest tests
with 24,807 assertions** passed. No step was skipped. Fresh `npm run build`
and `composer check-platform-reqs` passed; `git diff --check`, protected-method
body comparison and local documentation link/inventory checks passed. All 67
current guide inventory entries match physical files, with no missing or stale
entries. Exact local logs are under `/workspace/work/package-*.log`; they are
external verification evidence and are not commit candidates.

No push, PR, merge, deployment or production-data mutation is authorized. Full MySQL
migration verification and the full all-store browser suite were not rerun in
this follow-up; no migrations were changed. No live gateway calls, production
permission rehearsal or production-data mutation was performed.


## Category administration migration (2026-10-12)

User-authorized local-only work on `codex/category-admin-inertia` from fetched
`origin/develop` at `03734ff6c3fe97435b08cf8d4c0644c792a09663` (PR #384),
which includes PR #379's merge `a14f5dc160b84ccb691e79d167c982aa9adb90c7`.
The starting checkout was clean. No push, PR, merge, deployment or production-data
mutation was performed.

### Prerequisites, status and consumer evidence

All three category admin screens were Blade. The reconciliation prerequisite table
has no category-specific dependency, feature-use or business-policy hold. Category
forms use ordinary text/number/select/image fields, including a plain SEO textarea;
no rich-text editor is needed. The seller/classified/editor/chart/map holds remain
separate and unchanged. No checked-in approval claim was treated as authorization.

Read-only GitHub checks: PR #379 head `29c9f384` passed all 14 checks; merge SAST
run `38245990218` succeeded; deploy run `38245990290` and deployment `7013472103`
succeeded. The newer fetched develop base also has successful SAST `38260207944`,
deploy `38260208198` and deployment `7015964338`. Both deployment workflows found
existing successful tests coverage and skipped `Test the merged commit`. These
are observations of existing deployments, not requests or deployment approval.

Before removal, category route names, controller actions, view names, models,
translations, settings, image references, hierarchy utility, attribute pivot,
public/mobile resources and seller consumers were traced. Only the root admin
controller directly rendered the three replaced views; its remaining method was
an empty unused show. The replaced featured endpoint was consumed only by the
removed admin list's JavaScript. Navigation retains route names and points to the
new controller. Public `categories.all`, HomeController rendering, shop-web,
shop-seller, shop-api, V2 category/subcategory/home/filter/warehouse resources and
controllers are unchanged. `shop.categories.child_category` is still used by
seller, ordinary/digital product and classified forms, so it remains. The category
PDF and bulk-download handler also remain.

### Completed scope and retained behavior

- Three `Store/Admin/Product/Categories` pages using StoreAdminLayout, shared
  form/table/pagination/action components, canonical StoreUploadField, translation
  tabs, shared featured status button and Wayfinder; English/Arabic labels and RTL.
- Seven implemented route names and view/add/edit/delete gates preserved; explicit
  bound edit/update/delete routes, PUT/PATCH updates and DELETE deletion. Removed
  only the empty show and duplicate route registrations. Search remains on base
  names, with descending ordering priority and validated page-size selection.
- Dedicated store/update/filter/featured requests, narrow list and form resources,
  schema-aware bounds, valid languages/attributes/parent references, refusal of
  self/descendant/cyclic parents, and canonical image authorization. Existing valid
  foreign images can round-trip without permitting replacement or unauthorized
  previews. Shared preview rules are centralized for category, carrier and package
  forms without changing their behavior.
- Atomic category, translation, attribute-pivot, hierarchy and direct-product
  updates/deletion. Ordered hierarchy locks serialize admin hierarchy changes;
  validation runs again under those locks. Persistence failures roll back all
  affected tables. Cache invalidation retains the existing featured cache behavior.
- Base names change only for the default language; other translation edits still
  update shared fields. Ordering, physical/digital type, commission setting/rate,
  banner/icon/cover image, filtering attributes, slug generation/lowercasing, SEO
  title/description and existing `top`/featured state remain as established.
- Deletion retains immediate-child reparenting to the deleted row's parent,
  clearing only direct product category references and removing only the selected
  category's translations/pivot. No child cascade, classified-row cleanup or other
  business-policy change was introduced. `CategoryUtility` bodies are unchanged.
- Typed Category properties and CategoryTranslation relation; CategoryTranslation
  and the deleted root handler leave both analysis exclusions. Category was already
  analyzed. Utility/API and still-needed legacy exclusions remain. No casts,
  dependency manifests/locks, permission definitions/grants, flags, public/seller/
  mobile contracts, product/brand/editor migration or global upload policy change.

**Inherited hierarchy issue retained:** `CategoryUtility::move_level_up` and
`move_level_down` change levels by one and return after the first child branch.
Moves across multiple levels therefore do not recalculate the whole subtree;
deleting a row reparents all immediate children but adjusts only that branch's
levels. Tests pin both move directions and deletion. Correcting this behavior is
separate work requiring explicit scoped authorization; it does not block this
presentation migration, and no correction approval was inferred.

Current inventory: **64** backend Blade files (67 before category conversion),
**40** direct literal consumers of `shop.backend.layouts.app`, **3** of the secondary
layout, **0** of the blank shell; **1,047** `/temp` registrations and **435**
`/temp/admin` registrations. No category-admin Blade screens remain. The guide's
§5.1 filename inventory and §6 controller table are updated. Historical dated
counts remain historical evidence. Retained grouped counts: catalogue/reviews/SKU
12, sellers 13, trips 13, website settings 5, representative visits/maps 3, blog
forms 2, general/map settings 2, classified/dashboard/newsletter/ticket/server
screens 5, protected layouts/navigation/PDFs 9.

### Verification and limits

Existing dependencies were used with unchanged manifests and lockfiles. PHP 8.5.11
and Composer were already provisioned outside PATH and selected through the cloud
runtime. GD initially lacked WebP, which correctly refused canonical raster uploads.
An isolated task runtime rebuilt GD with WebP through the managed proxy, using a
read-only public CA mount; the extension/libraries are in task scratch space, not
source changes. Composer platform checks passed. A stale `public/hot` marker had
no running Vite process and was preserved outside the checkout for production-
asset verification.

Required `python3 .claude/scripts/pre_push_check.py`: all lint/format/type/test
stages passed, with no verification stage skipped. Full Vitest: **2,096 tests in
235 files**. Full Pest: **5,196 tests / 25,453 assertions**. PHPStan: zero errors.
Focused category/model/permission/route/exclusion/guard/Blade-CSP and canonical
upload/carrier/package checks: **316 tests / 1,987 assertions**. Focused category
pages/fields/status/menu frontend checks: **27 tests in four files**. Production
build and fresh Wayfinder generation passed. Final review removed two unused
edit-query columns; the category suite passed again (**84 tests / 586 assertions**)
and PHPStan again reported zero errors. Existing chunk-size and plugin-time build
advisories remain.

Selected HTTPS Chromium workflows: **5/5 passed**, one worker, zero retries,
with CSP enforced and video settings enabled. The task selected installed system
Chromium and FFmpeg; Playwright's bundled FFmpeg was absent. Browser checks cover
canonical raster upload and all three category image pickers, parent-link
navigation, category CRUD, type/order/attributes/commission/SEO round trips,
English/Arabic translation tabs and locale switching, featured status, native
cancel/accept deletion, RTL, dark contrast, native integer validation, refused
write with retained name/order/SEO draft and no inserted category, sidebar Inertia
visits, retained public category Blade and mixed Blade/Inertia staff workflows.
Navigation waits use the rendered translation language and localized locale labels.
Retained login/Blade third-party script and Permissions-Policy warnings are outside
this migration; no globally clean browser-console claim is made.

Local limits: MySQL migration/concurrency checks and the full browser suite were
not run; no migrations changed. Production data/grants and external services were
not exercised. Deferred source dispositions and unrelated owner decisions remain
unchanged. Exact verification logs and temporary runtime/browser artifacts are in
`/workspace/work/category-*`, outside the commit.

## Brand administration migration (2026-10-12)

User-authorized local-only work on `ccr-deb0d484-n8nxxo` from fetched
`origin/develop` at `f39b065d8b376d324a0ec965c333070ffe2cbf6f` (PR #385 merge).
The starting checkout was clean and equal to that commit. No push, PR, merge,
deployment or production-data mutation was performed.

### Prerequisites and consumer evidence

Both brand admin screens were Blade. The prerequisite table has no brand-specific
dependency, feature-use or business-policy hold, and the forms need no editor.
Before removal, routes, root controller, views, models, translations, image
references, permissions, API/seller/public consumers and tests were traced. Only
the root admin controller rendered the two views; its `create`/`show` were empty
and unused. The sidebar link uses the route name; `admin_sidenav`'s
`areActiveRoutes(['brands.create', ...])` tolerates the removed name. Public
`brands.all`, HomeController/SearchController/CustomerProductController slug
lookups, storefront/seller/customer selects, `Api/V2/BrandController` (index/top),
`FilterController::brands`, seller/bulk-upload readers, ImportBrands `serial` writer,
NewFetchProducts `serial` lookup, offer/campaign brand pickers and the brand PDF
download are unchanged. `HandleStoreBladePagesTest`, `ReconstructedMigrationAuditTest`
and `RouteServiceProviderTest` used brand admin URLs as retained-Blade or
resource-duplicate examples; they now use the still-Blade digital-product routes.
`LegacyStoreStateChangingRoutesTest` keeps both brand cases, adapted to DELETE.

**`brands.order_level`:** absent from `create_brands_table`, the legacy-columns
migration (only `serial` is added), legacy `shop.sql` 6.5.0, all 70 legacy
`sqlupdates/v*.sql` (`v42.sql` adds `order_level` to `categories` only) and all
107 legacy migrations at `arb-rajab/albaraka-holland@75531338fa8190891c6e334620e461f6c79fe5be`
(read-only sparse clone outside this repository). The legacy repository's own
admin view and controller have the identical read/write. The converted screens
omit the field; ordering stays by name and no schema/replacement field was added.
Production schema was not available for inspection.

### Completed scope and retained behavior

See §14 of the [migration guide](rules/store-backoffice-migration.md). Brand
deletion still hard-deletes the brand's `products` rows (now transactional); that
conflicts with the shared-database single-writer rule and is recorded as an owner
decision, not changed.

### Verification and limits

Existing dependencies were used; manifests and lockfiles are unchanged. PHP 8.5,
Composer dev tools, Wayfinder and assets came from this session's setup.
`npm run build` failed only on fetching the remote `fonts.bunny.net` font
(`ENOTFOUND`); the documented `.claude/scripts/build_without_fonts.py` production
build passed and was used for browser runs. Fresh Wayfinder generation passed.

- `python3 .claude/scripts/pre_push_check.py`: ESLint, Prettier, vue-tsc, full
  Vitest (**2,109 tests in 237 files**), Pint, PHPStan (zero errors) and full Pest
  (**5,254 tests / 25,858 assertions**) passed; no stage skipped.
- Focused brand/discount/route/state-changing/Blade-CSP/audit/legacy panel and
  permission/model checks: **124 tests / 675 assertions**; refactored upload rule
  plus category/carrier/package, exclusion, shell, shared-props, guard-isolation,
  storefront, API, campaign and bulk-upload checks: **396 tests / 2,610 assertions**.
- HTTPS Chromium (installed `/opt/pw-browsers` build), enforced CSP, one worker,
  zero retries, fresh e2e databases: brand spec **3/3**, then brand, catalogue
  forms, categories, carriers, customer packages, staff/reference coexistence and
  legacy uploader **18/18**. After the inline-add remount fix the brand spec passed
  **3/3** again. Two first-run failures were spec-authoring errors (a fixture used
  `firstOrNew` on a model without fillable attributes; a slug assertion read the
  preserved live input instead of the saved value) and were corrected.

Limits: MySQL, production schema/data, deployed grants and external services were
not exercised; the full browser suite was not run. Brand-discount has no browser
spec. Production may differ from the inspected schema sources for `order_level`.

## Digital product list migration (2026-10-12)

Work on `claude/digital-products-list-migration-6tpmmr` (PR #387) from fetched
`origin/develop` at `f39b065d8b376d324a0ec965c333070ffe2cbf6f` (PR #385). The
designated branch had no commits beyond that base. PR #386 (brands) was then
merged into `develop` (`6de7128`), and `develop` was merged into this branch to
resolve the resulting conflicts. Push and PR were authorized by the user; no
merge, deployment or production-data mutation was performed.

### Prerequisites and audit evidence (develop line numbers)

Only the list converts. The create/edit forms use the Blade rich-text editor,
held by the Tiptap dependency decision in the table above. No other
digital-product dependency, feature-use or business-policy hold exists.

- **Index** (`app/Http/Controllers/DigitalProductController.php:43-53`):
  `orderBy('created_at', 'desc')`, `name like %search%` when present,
  `digital = 1`, `paginate(10)`. The view linked each name to the public
  `product` route by slug (`index.blade.php:48`) and showed edit, delete and
  download under `@can('add_digital_product')` (`:73-87`), while the routes
  gate `edit_`, `delete_` and `download_digital_product` (controller `:31-35`).
- **Switches** (`index.blade.php:120,137,154`) posted to `products.todays_deal`,
  `.published`, `.featured` (`routes/shop-admin.php:117-120`), root
  `ProductController::updateTodaysDeal/updatePublished/updateFeatured`
  (`:629-709`), gated `product_edit` (`:65`). They return `1`/`0` with no
  redirect, accept any product id, and `updatePublished` writes stock before its
  seller-subscription refusal. Not usable from Inertia; left unchanged for the
  Blade product lists.
- **Destroy** (`:250-261`): translations, stocks, then `Product::destroy`,
  untransacted; `POST /digitalproducts/destroy/{id}` (`routes/shop-admin.php:144`)
  for any product id.
- **Download** (`:264-276`): `decrypt($request->id)`, `Upload::findOrFail`, S3
  download or `public/` file, else no return value (empty 200); a bad ciphertext
  is a 500. Only consumer of the encrypted id in the repo: the removed list.
- **Consumers:** seller `shop-seller.php:48-55` runs `Seller\DigitalProductController`
  (group namespace); `shop-api.php:64` runs `Api\V2\DigitalProductController`;
  `shop-web.php:286` runs `PurchaseHistoryController::download`
  (`shop-web.php:20` imports the root class unused). Blade create/edit use
  `digitalproducts.store`/`.update`/`.edit`; the Blade sidebar uses
  `areActiveRoutes` on `.index`/`.create`/`.edit`; `storeAdminMenu.ts` imported
  the root `index`. `ProductController.php:388,423` redirect digital products
  to URLs without `/temp` (inherited debt, unchanged).

### Completed scope

- `Store\Admin\DigitalProductController` (index, destroy, download, three
  switches), `FilterDigitalProductsRequest`, `UpdateDigitalProductFlagRequest`,
  `DigitalProductResource`; ten explicit registrations keep every consumed
  route name; the root controller keeps only create/store/edit/update.
- `Store/Admin/Product/DigitalProducts/Index.vue` with StoreAdminLayout,
  DataTable, Pagination with selector, StoreStatusButton, TableActionLink,
  `confirmDelete`, `useStoreAccess()` and Wayfinder; create/edit/download are
  full-load `<a>` links. English/Arabic `store.digitalProducts.*` keys and one
  `messages.store_digital_product_package_limit` backend string. Sidebar entry
  is an Inertia visit. Only `digital_products/index.blade.php` was removed.
- Deletion and switches run in transactions with the row locked and accept
  only digital products. Download keeps the encrypted id and returns 404 for an
  undecryptable/non-integer id, a missing upload or an absent local file.
- Owner decisions recorded, behaviour unchanged: product hard deletion (shared
  with brand deletion in PR #386) and the published switch as a second writer
  of `products.published`.
- No dependency, manifest, lock, migration, permission definition, role grant,
  public/seller/mobile contract or shared ProductController change.

### Merge with PR #386

PR #386 had retargeted `HandleStoreBladePagesTest`, `ReconstructedMigrationAuditTest`
and `RouteServiceProviderTest` (plus the provider docblock) from brands to the
then-Blade digital product routes. The merge keeps this branch's targets:
general settings and the `sellers` resource, which remain Blade and
resource-registered. Locale files keep both `store.brands` and
`store.digitalProducts`; the menu test lists both Inertia URLs. The migration
guide keeps the brand follow-up as §14 and renumbers this one §15.

### Verification and limits

PHP 8.5 and the dev tools came from the SessionStart setup (`wait_for_setup.py`
exit 0); `composer.lock` and all manifests are unchanged. Laravel Boost was not
connected; repository files and installed sources were used instead.

- `php artisan wayfinder:generate --skip-routes --with-form`: generated.
- `python3 .claude/scripts/pre_push_check.py` on the final tree: every stage
  passed, none skipped. ESLint, Prettier and vue-tsc clean; Vitest **2,105 tests
  in 236 files**; Pint passed; PHPStan **0 errors**; Pest **5,237 tests / 25,839
  assertions** (41 in `DigitalProductControllerTest`). `git diff --check` clean.
- Focused: `DigitalProductControllerTest` 41 tests / 386 assertions; route
  provider, Blade-page CSP, reconstructed audit, state-changing routes, admin
  permissions, temp-route reachability, middleware conversion, seller product
  authorization, store API, exclusions, shared props, shop route access and
  admin panel suites 94 tests; page and menu Vitest 20 tests. Mutating the
  destroy transaction and the seller-package refusal failed three tests, as
  intended, before being reverted.
- Production build: `npm run build` fails only on `getaddrinfo ENOTFOUND
  fonts.bunny.net`; `python3 .claude/scripts/build_without_fonts.py` passed and
  the manifest contains the new page.
- Playwright over local HTTPS with CSP enforced, one worker, zero retries,
  `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/opt/pw-browsers/chromium` (Chromium
  1194; Playwright 1.62.1): the new `store-digital-products.spec.ts` 2/2, and
  with the catalogue-forms, categories, legacy-uploader, reference-coexistence
  and staff-coexistence specs **13/13**. Covered: switch round trips and their
  persisted writes, native delete cancel/accept, full-load (non-Inertia) hops
  into the retained Blade create/edit, RTL, dark contrast of link, header and
  cell text, and the sidebar Inertia visit.

- After merging `develop` (`6de7128`, PR #386): fresh Wayfinder generation,
  `build_without_fonts.py` build (`npm run build` again failed only on
  `fonts.bunny.net`), and `pre_push_check.py` with every stage passing and none
  skipped: Vitest **2,118 tests in 238 files**, PHPStan **0 errors**, Pest
  **5,295 tests / 26,244 assertions**. Digital-product, brand, route-provider,
  Blade-CSP, audit and state-changing-route suites: **129 tests**. Playwright
  under the same settings, adding #386's `store-brands.spec.ts`: **16/16**.
  Registrations after the merge: **1,043** `/temp`, **431** `/temp/admin`.

Not run: MySQL (locking/FK behaviour is verified on SQLite only), production
data or role grants, S3 against a real bucket (faked disk only), and the full
browser suite.

## In-house and all-products list migration (2026-10-12)

Work on `claude/migrate-admin-product-lists-2q0qog` from fetched `develop` at
`2aafc217478cb8e401340554718587d05662da25` (the PR #387 merge). The branch had
no commits beyond that base. It merged into `develop` as PR #388, merge commit
`046be2005c3019584483a44eecefddd39116a831`. For this session only, the owner authorized the
pull request, its merge into `develop` with the merge method, and `[skip ci]`
on every commit, the PR title and the merge commit. **No PR CI, no PR or merge
SAST and no `develop` deploy ran for this PR or its merge**, so the checks below
are the only verification. No other branch, deployment or production data was
touched.

### Prerequisites and audit evidence (develop line numbers)

Only the in-house and all-products lists convert. Seller administration remains
decision-blocked (prerequisite table above), so `products.seller` and the
shared `shop/backend/product/products/index.blade.php` stay. Product
create/edit use the Blade rich-text editor, held by the Tiptap decision.

- **In-house** (`app/Http/Controllers/ProductController.php:74-120`):
  - scope `added_by = admin`, `auction_product = 0`, `wholesale_product = 0`
    (`:83`) and `digital = 0` (`:117`);
  - the unvalidated `type` sort `explode(',')` → `orderBy` (`:85-91`);
  - a single-phrase search over `name`/`ar_name`/`barcodes` with ungrouped
    `orWhere` (`:92-100`, barcode comment `:98`);
  - `published`/`for_sale`/`featured` always matched to `has()` (`:102-106`),
    so with nothing ticked only all-off products list;
  - `category_id` (`:112-115`), then `created_at desc` and `paginate(15)`
    (`:117`).
- **All products** (`:164-254`):
  - `user_id` (`:178-181`);
  - a multi-word search over `name`/`ar_name`/`mat_id`/`barcodes`
    (`:182-197`);
  - the same sort (`:199-205`);
  - `allProducts`, then the first of `allDeleted`/`allActive`/`allForSale`/
    `allFeatured`, then exact matching of four switches, else
    `deleted_from_api = false` (`:207-242`);
  - category (`:245-248`), `paginate(15)` with **no order** (`:250`).

  The task brief's "`created_at desc`" applies to the in-house list only.
- **Barcodes.** The merged `Product::barcodes()` reads WMS
  `wms_product_barcodes` (`app/Models/Product.php:168-171`).
  `NewFetchProductsCommand.php:363-369` stores each `Mat_ID` in the store's
  `product_barcodes` (Task BS-152), which `storeBarcodes()` reads
  (`Product.php:816-819`).
- **Blade actions** (`index.blade.php`):
  - For Sale/Active/Featured switches post to `products.update_sale`,
    `.published` and `.featured` (`:329-367`, `:469-543`).
  - Today's deal is commented out (`:234`, `:319-328`).
  - Approval shows only for `Seller` with `product_approve_by_admin`
    (`:237`, `:348-358`).
  - Duplicate (`:399`), delete (`:407`) and bulk delete (`:549-567`).
  - The fetch button shows only on non-seller lists under `add_new_product`
    (`:9-22`, `:569-597`).
- **Handlers** (root controller):
  - `updateSaleStatus` (`:824-851`) and `updatePublished` (`:638-674`) zero or
    restock stock and unfeature. `updatePublished` checks the seller
    subscription after writing stock, `updateFeatured` is at `:699-709`, and
    each takes any id and answers `1`/`0`.
  - `destroy` (`:557-581`) deletes translations, stocks, taxes, variations and
    store barcodes, `Product::destroy`, then `Cart::where(...)->delete()`
    (soft delete), untransacted. `bulk_product_delete` (`:583-592`) loops it
    and answers `1`.
  - `duplicate` (`:600-621`) does `replicate()` with a slug suffix plus stock
    and tax copies, through `Product::find` (null → 500).
  - `fetch` (`:774-821`) queues the `cron_jobs` row
    `fetching-products-by-admin` (JSON `{success}`, 500 when busy). It is
    ungated (`tests/Feature/LegacyAdminPermissionsTest.php:46`) and consumed
    by `check:commands` (`app/Console/Commands/CheckForCommands.php:52-90`) →
    `import:all-products-v2`.
  - Gates are in `middleware()` (`:58-69`).
- **Consumers:**
  - `routes/shop-admin.php:110-127`;
  - the seller list's same-name routes;
  - `admin_sidenav` `areActiveRoutes` (`:96`, `:107`);
  - `storeAdminMenu.ts` imported the root `admin_products`/`all_products`;
  - root `store` redirects to `products.admin` (`:363`);
  - `choice_options_script` is used by create/edit, and `sku_combinations*` by
    the root and seller SKU helpers;
  - seller panel `Seller\ProductController`, V2 product APIs, the storefront
    `product` route and `products:sync` are separate;
  - the `admin_product_edit`/`seller_product_edit` digital redirects without
    `/temp` (`:388`, `:423`) are inherited debt.

### Completed scope

- **Backend.**
  - `Store\Admin\ProductController` (`inHouse`, `all`, three switches,
    duplicate, destroy, bulk destroy).
  - `FilterInHouseProductsRequest`, `FilterAllProductsRequest` (shared
    `FiltersStoreProductList`), `UpdateProductListFlagRequest`,
    `ProductListActionRequest`, `BulkDeleteProductsRequest` (shared
    `ValidatesStoreProductList`), the `StoreProductList` enum and
    `ProductListResource`.
  - `products.admin`/`.all` keep their URIs and gates.
  - Six `products.list.*` routes take `list` and are list-scoped.
- **Shared switch path.** `Concerns\UpdatesStoreProductFlags` now serves the
  digital-product and product lists. The digital controller's own copy moved
  into it, and `messages.store_digital_product_package_limit` became
  `messages.store_product_package_limit` (same text). The root jQuery
  endpoints, `products.fetch` and the seller list are unchanged.
- **Frontend.**
  - `Store/Admin/Product/Products/Index.vue` with StoreAdminLayout,
    DataTable, Pagination (selector), StoreStatusButton, TableActionLink,
    `confirmDelete`, `useStoreAccess()` and Wayfinder.
  - Full-load create/edit/view links; `useHttp` fetch button.
  - English/Arabic `store.products.*` (57 keys).
  - Sidebar in-house/all entries are Inertia.
- **Deliberate changes.**
  - 20 per page with the selector.
  - The `type` sort allowlist (6 values, 422 otherwise).
  - Explicit `id` order for all-products.
  - Grouped in-house search, and barcode search over both tables.
  - Normalized GET booleans.
  - List-scoped, transactional, locked writes.
  - Bulk deletion authorizes every member under lock before deleting any.
  - Caches clear once per request.
- **Owner decisions recorded, behaviour unchanged:**
  - product hard deletion (as for brands and digital products);
  - duplicate and the fetch import as further writers of `products`;
  - the published switch as a writer of `products.published`.
- **Unchanged:** no dependency, manifest, lock, migration, permission
  definition, role grant, flag, Blade view or public/seller/mobile contract
  changed.

### Verification and limits

PHP 8.5 and the dev tools came from the SessionStart setup (`wait_for_setup.py`
exit 0). The worker process restarted once mid-session; the working tree, build
and generated actions were intact afterwards. Laravel Boost was not connected
for most of the session, so repository files and installed sources were used.

- **Generation and build.**
  - `php artisan wayfinder:generate --skip-routes --with-form` generated
    actions.
  - `npm run build` failed only on `getaddrinfo ENOTFOUND fonts.bunny.net`.
    `python3 .claude/scripts/build_without_fonts.py` passed, and was rerun
    before the browser runs.
- **Full gate.** `python3 .claude/scripts/pre_push_check.py` passed every stage
  with none skipped:
  - ESLint, Prettier and vue-tsc clean;
  - Vitest **2,127 tests in 239 files**;
  - Pint passed;
  - PHPStan **0 errors**;
  - Pest **5,351 tests / 27,223 assertions**.
- **Focused PHP checks.**
  - `ProductControllerTest`: **56 tests / 979 assertions**.
  - `DigitalProductControllerTest` after the shared-path refactor: 41 / 386.
  - Middleware conversion, state-changing routes, admin permissions, route
    provider, Blade CSP, reconstructed audit, temp-route reachability, shop
    route access, exclusions, shared props, shell and seller authorization
    suites: **70 tests / 357 assertions**.
  - Mutating the in-house search grouping, the store-barcode match and the
    destroy transaction failed four tests, as intended, before being reverted.
- **Focused frontend checks.** Page and menu Vitest: **20 tests**. Scoped
  ESLint/Prettier and `npm run types:check` passed.
- **Browser.** Playwright 1.62.1 over local HTTPS with CSP enforced, one worker,
  zero retries and `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/opt/pw-browsers/chromium`
  (Chromium 1194).
  - The new `store-products.spec.ts` passed **2/2**. It covers:
    - switch round trips and their persisted writes;
    - native single and bulk delete cancel/accept;
    - full-load hops into the Blade edit form and the Blade seller list;
    - Inertia sidebar visits to both lists;
    - RTL, and dark contrast of name, header, cell and preset link.
  - With the brands, digital-products, categories, catalogue-forms,
    staff/reference coexistence and legacy-uploader specs: **18/18**.
  - Two first runs failed on spec authoring: the all list's first page held
    other seeded products, and assets predated a new element id. Both were
    corrected.
- **Inventory.** **61** backend Blade files; **37**/**3**/**0** literal
  consumers of the app/layout/blank shells; **1,049** `/temp` and **437**
  `/temp/admin` registrations, up from 1,043/431 by six action routes.

Not run: MySQL (locking and foreign-key behaviour is verified on SQLite only),
production data or role grants, the real Otajer import behind `products.fetch`,
and the full browser suite. Because of `[skip ci]`, GitHub CI, SAST and deploy
did not run for this PR or its merge.

## General settings migration and Google Map hold (2026-10-12)

Work on `ccr-b39fe4ad-n2p9xs` from fetched `develop` at
`e3eb0f5315b4f66c25e9db176316b9655759e047` (the PR #390 merge, after the
PR #389 merge `a14c6595b4c72ced24dc43a0d24ba5ccaff43723`). The branch had no
commits beyond that base. For this session only, the owner authorized
the pull request (#391), its merge into `develop` with the merge method, and
`[skip ci]` on every commit, the PR title and the merge commit. **No PR CI, no
PR or merge SAST and no `develop` deploy ran for this PR or its merge**, so the
checks below are the only verification. No other branch, deployment or
production data was touched.

### Prerequisites and audit evidence (develop line numbers)

- **Google Map settings held (map decision).**
  `setup_configurations/google_configuration/google_map.blade.php:105-107`
  includes `shop.frontend.partials.google_map` when `google_map == 1`; the
  partial loads `https://maps.googleapis.com/maps/api/js?…&libraries=places`
  (`:117`) and creates a `google.maps.Map` (`:17`). The default-location inputs
  are `readonly` (`:81`, `:89`) and only the map fills them. The brief required
  stopping here, so only general settings converted.
- **General settings handler.** `BusinessSettingsController.php:28` (gate),
  `:45-48` (view), route `routes/shop-admin.php:336`. The form posted `types[]`
  to the generic writer (`:135-191`), gated
  `general_settings|footer_setup|website_appearance|edit_website_page` (`:31`).
- **Timezone.** Displayed from `app_timezone()` = `config('app.timezone')`
  (`app/Http/Helpers.php:1022`), hardcoded `'UTC'` (`config/app.php:146`);
  options from `timezones()` (`Helpers.php:1011`). The writer sent it only to
  `overWriteEnvFile('APP_TIMEZONE')` (`:149-150`), which refuses, logs and
  flashes; no `timezone` row was ever stored. `site_name` also went to the
  refusing `overWriteEnvFile('APP_NAME')` (`:146-147`) and then to its row.
- **Readers.** `system_logo_white`: `inc/admin_sidenav.blade.php:5-7`.
  `system_logo_black`: `shop/frontend/seller_login.blade.php:12-13`,
  `deliveryboy_login.blade.php:12-13`. `admin_login_background`: none (the
  store login reads `login_page_image`, `shop/auth/login.blade.php:162`).
  `site_name`: `shop/backend/layouts/app.blade.php:74`,
  `shop/seller/layouts/app.blade.php:85`, `admin_sidenav.blade.php:7,10`,
  `seller_sidenav.blade.php:7,10`, `invoices/invoice.blade.php:88` (PDF),
  `shop/emails/invoice.blade.php:80` (mail),
  `Api/V2/BusinessSettingController.php:17` (`PUBLIC_KEYS`). `get_setting()`
  (`Helpers.php:1131-1157`) reads the first row of a type.
- **Tests that used general settings as the Blade example.**
  `HandleStoreBladePagesTest.php:29,31,37,60`,
  `ReconstructedMigrationAuditTest.php:43`,
  `SettingsReconciliationTest.php:10-17` (`site_name` as the permitted key),
  `ReferenceSettingsIsolationTest.php:11-14`,
  `store-staff-coexistence.spec.ts:43-66`, `store-legacy-uploader.spec.ts:9-72`.
  `RouteServiceProviderTest.php:57` uses only `sellers`.

### Completed scope

- **Backend.** `Store\Admin\GeneralSettingsController` (`edit`, `update`),
  `UpdateGeneralSettingsRequest` (`KEYS`, `IMAGES`), reusing
  `UpdatesStoreSettings`, `ProvidesStoreUploadPreviews`,
  `ValidatesWebsiteSettings::refuseUnexpectedSettingInput()` and
  `ValidatesStoreUploadReference::retainableImageUploadRule()`. New route
  `general_setting.update`; `general_setting.index` keeps its name, URI and
  gate. The generic writer reserves `UpdateGeneralSettingsRequest::KEYS`.
  `messages.store_timezone_environment_managed` in English and Arabic.
- **Frontend.** `Store/Admin/Setup/GeneralSettings/Index.vue`, the
  `StoreGeneralSettings` types and fixture, 9 `store.generalSettings.*` keys
  per locale, and the sidebar entry as an Inertia visit.
- **Removed.** `general_settings.blade.php` and root `general_setting` with its
  middleware entry. No other view, partial or shell was removed.
- **Deliberate changes.** Validation (schema width, upload ownership, timezone
  identifiers), refusal of a timezone other than the configured one with a
  field error, and no `.env` refusal log or flash on a normal save.
- **Unchanged.** No dependency, manifest, lock, migration, permission
  definition, role grant, feature flag, `.env` write, stored format or
  public/seller/mobile/mail/PDF contract changed.

### Verification and limits

PHP 8.5 and the dev tools came from the SessionStart setup (`wait_for_setup.py`
exit 0). Laravel Boost was not connected, so repository files and installed
sources were used.

- **Generation and build.**
  - `php artisan wayfinder:generate --skip-routes --with-form` generated
    `GeneralSettingsController` actions.
  - `npm run build` failed only on `getaddrinfo ENOTFOUND fonts.bunny.net`
    (the proxy answers 403). `python3 .claude/scripts/build_without_fonts.py`
    passed, and its manifest served the Pest and browser runs.
- **Full gate.** `python3 .claude/scripts/pre_push_check.py` passed every stage
  with none skipped:
  - ESLint, Prettier and vue-tsc clean;
  - Vitest **2,139 tests in 240 files**;
  - Pint passed;
  - PHPStan **0 errors**;
  - Pest **5,423 tests / 27,585 assertions**.
- **Focused PHP checks.**
  - `GeneralSettingsControllerTest`: **36 tests / 215 assertions**.
  - Blade CSP, reconstructed audit, settings reconciliation and isolation,
    Google Map, middleware conversion, admin permissions, state-changing
    routes, route provider, `.env` protection, temp-route reachability, shop
    route access, storefront renders and data exposure: **372 tests / 1,233
    assertions**.
  - Mutations failed tests as intended before being reverted: dropping the
    foreign-input refusal (2 failures), dropping the configured-timezone
    refusal (2) and un-reserving the keys in the generic writer (10).
- **Focused frontend checks.** Page and menu Vitest: **18 tests**. Scoped
  ESLint/Prettier and `npm run types:check` passed.
- **Browser.** Playwright 1.62.1 over local HTTPS with CSP enforced, one worker,
  zero retries and `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/opt/pw-browsers/chromium`.
  - The new `store-setup-settings.spec.ts` passed **3/3**: the name and a
    canonical logo pick survive a reload and reach the Blade admin shell, the
    timezone stays `UTC` and unstored, a refused timezone keeps the draft in
    Arabic RTL with dark contrast, and the sidebar visit is Inertia.
  - With the retargeted coexistence and legacy-uploader specs and the brands,
    digital-products, products and categories specs: **16/16** in 7 files.
  - The first run failed on spec authoring: it asserted no `'unsafe-inline'`
    anywhere in the policy, but `style-src` carries it on every page. The
    assertion now reads `script-src` only.
- **Inventory.** **60** backend Blade files; **36**/**3**/**0** literal
  consumers of the app/layout/blank shells; **1,050** `/temp` and **438**
  `/temp/admin` registrations, up from 1,049/437 by one POST.

Not run: MySQL, production data or role grants, the real Google Maps script,
and the full browser suite. Because of `[skip ci]`, GitHub CI, SAST and deploy
did not run for this PR or its merge.
