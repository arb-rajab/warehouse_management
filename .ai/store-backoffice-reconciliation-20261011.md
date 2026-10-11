# Store back-office reconciliation — 2026-10-11

Implementation handoff, not final combined verification. Integration branch:
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
| Unavailable audit | `aed3feab5b6edd95fb941ee92a455e058403def5` | Explicitly excluded | Unavailable; not recovered or included. Audit reconstruction belongs to the next prompt |

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
- Reconstruct the explicitly unavailable audit in the next prompt. Perform final
  combined verification later; do not treat this handoff as deployment approval.

The [migration guide](rules/store-backoffice-migration.md) lists actual retained
Blade files and registered migrated controllers/routes, without sibling progress
counts. Selected cherry-picks and follow-up reconciliation commits are preserved
on the integration branch for the next prompt.
