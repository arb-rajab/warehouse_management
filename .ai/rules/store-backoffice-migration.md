---
paths:
  - 'resources/views/shop/backend/**'
  - 'resources/js/pages/Store/**'
  - 'resources/js/layouts/StoreAdminLayout.vue'
  - 'resources/js/lib/storeAdminMenu.ts,resources/js/lib/storeAccess.ts'
  - 'resources/js/components/StoreAdminNavLink.vue,resources/js/components/StoreStaffFormFields.vue,resources/js/components/StoreRoleFormFields.vue'
  - 'app/Http/Controllers/Store/**,app/Http/Requests/Store/**,app/Http/Resources/Store/**'
  - 'app/Http/Middleware/HandleInertiaRequests.php,app/Http/Middleware/HandleStoreBladePages.php,app/Http/Middleware/SendSecureHeaders.php'
  - 'routes/shop-admin.php'
  - 'tests/Feature/Store/**,tests/e2e/store-*.spec.ts'
---

# Moving the store back office from Blade to Inertia

## 1. Decision and scope

On 2026-10-05 the owner approved rewriting the store's back office in
Inertia v3 + Vue 3 + TypeScript. That overrides the "out of scope for the
merge" note in legacy-albaraka-holland.md §5, which records the approval.

- **In scope:** `resources/views/shop/backend/**`, served under `/temp/admin`.
- **Not in scope:** the storefront, the seller area, the store's auth pages,
  its emails and its PDFs. They stay Blade.

The back office moves one module at a time. While it does, converted and
unconverted pages share `/temp` (§2.6).

- **Starting count:** there were 201 Blade files, not the 214 that legacy §5 states:
  - 200 in module folders, plus `dashboard.blade.php`;
  - the per-module counts in §6 add up to 201.
- **Pilot:** staff, 6 views, now converted. It sets the pattern (§3).
- **Order:** §4 lists the remaining batches.
- **Not ported:** §5 lists the views to delete, and the PDFs that stay server-rendered.

### Cleanup gate (verified 2026-10-10)

Final shell cleanup is not unblocked. Batches 0 and 1 are converted. The
current tree has 174 Blade files under `resources/views/shop/backend` after
deleting the independently dead `layouts/blank` and batch 1's 20 views; 138
still extend `layouts/app`. Batches 2-11 in §4 therefore remain outstanding.

- Keep `layouts/app`, `inc/admin_sidenav` and `inc/admin_nav`: the layout is
  still the sole consumer of both includes and still serves those 138 pages.
- Keep `layouts/layout`: its exact consumers are the seller login, delivery-boy
  login and store 503 page.
- Keep the Inertia root `resources/views/app.blade.php`.
- Keep `invoices/invoice` and `downloads/brand`, `category` and `user`: the
  admin and seller PDF controllers still render them.
- Keep `HandleStoreBladePages`, `SendSecureHeaders`' request override and
  `store.blade_csp`. The override applies to all three store web groups, and
  retained storefront, seller, authentication and back-office Blade pages
  still require inline and third-party assets. No directive is proven obsolete
  while that route-wide consumer set remains.
- Keep `system/server_status` and its route until the documented owner call is
  supplied. The technical recommendation in §5 is not approval.

`StoreBackOfficeShellTest` ratchets these retained boundaries and the removal
of `layouts/blank`. Re-run this trace after every remaining module is converted
or explicitly resolved; only then may batch 12 remove the admin shell and
re-audit the CSP directives and legacy assets.

## 2. Foundation decisions

### 2.1 Shared props are guard-aware

`HandleInertiaRequests::share()` treats a request as a store request when its
route carries `UseStoreAuthGuard`, which every `/temp` web group starts with.
It reads the route, not the default guard, because the default only becomes
`store` once that middleware has run.

On a store page it shares:

- `name`;
- `locale`: the store locale that `Language` set;
- `auth.user`: `{id, name, email}` of the `store` guard's user. This is the
  same narrow shape as WMS; middleware.md explains why it stays narrow.
- `store`:
  - `rtl`: from `languages.rtl`;
  - `permissions`: the user's store permission names;
  - `isSuperAdmin`;
  - `isRaizerAdmin`;
  - `settings`: one boolean per `STORE_MENU_SETTINGS` key.

Everything except `name` is a closure, because `Language` runs after this
middleware. WMS pages keep their props exactly as before, with no `store` key.
`app.blade.php` takes `dir` from `store.rtl` when it is there, and otherwise
keeps WMS's `locale === 'ar'`.

- **Permissions:** `isSuperAdmin` is shared as a flag rather than expanding to
  every permission row. That mirrors `Gate::before()`, which grants a Super
  Admin abilities even when no row exists for them.
- **Settings in sync:** `StoreInertiaSharedPropsTest` fails if
  `lib/storeAdminMenu.ts` reads a setting the middleware doesn't share, or
  the other way round.
- **Tests:** tests/Feature/Store/StoreInertiaSharedPropsTest.php.

### 2.2 StoreAdminLayout and the sidebar

`layouts/StoreAdminLayout.vue`:

- a sidebar with collapsible groups;
- the account block at its foot: storefront link, profile, `LanguageSwitcher`,
  and `LogoutLink` posting to the store's `shop.logout`;
- below `lg`, a menu button.

**Gates.** The menu data is `lib/storeAdminMenu.ts`. It is the Blade sidebar
(`inc/admin_sidenav`), with every entry gated as Blade gated it:

- `permission`, which may be any-of;
- `setting`: a business setting that switches the section on. The vendor's
  `raizer_admin` account passes every setting gate;
- `raizerAdmin`: vendor-only entries.

`visibleStoreMenu()` drops a group with no visible entry, which is what
`@canany` did.

**Entries:** each entry is `inertia: true` (an Inertia `<Link>`) or not (a
plain `<a>`, i.e. a full page load). Staff, roles and batch 1's shipping/tax
reference and configuration pages are Inertia so far.

**How it differs from the Blade sidebar, on purpose:**

- **Dropped:**
  - the 12 removed-addon blocks: their routes no longer exist;
  - the stray duplicate top-level Shipping group;
  - System → Server status: it is a `Route::view` with no Wayfinder action,
    and §5 marks the page for deletion.
- **Fixed:**
  - Home Banners now links inside `/temp`;
  - Product reviews and Subscribers link to the admin URI. Their route names
    had gone to storefront routes.
- **Gates follow the route's own permission where Blade disagreed**, so a link
  never leads to a 403:
  - customer archive: `delete_customer`;
  - notifications: `add_notification`;
  - brand discount: `view_all_brands`;
  - Home Banners: `edit_website_page`.
- **Not built yet.** They belong with the dashboard batch:
  - the badge counts (seller verifications, unread tickets, conversations);
  - the menu search;
  - the notifications dropdown;
  - Clear Cache.

**Shared with WMS.** Both layouts genuinely need these:

- `components/SkipToContentLink.vue`;
- `lib/navigation.ts` `isActiveNavUrl()`: the longest-match active link,
  compared on paths;
- `LogoutLink`'s `href` prop;
- `ResourceFormPage`'s `layout` and `wide` props.

`AdminLayout` and `AccountMenu` are otherwise unchanged. The store's sidebar
and WMS's top bar share nothing else.

### 2.3 Translations

**Decision: back-office UI strings move into `resources/js/locales/{en,ar}.json`
under `store.*`.** Values that live in the database are translated on the
server and sent as data.

- **DB-held values:** permission and section names, role names
  (`role_translations`), category names and the like.
- **Example:** `RoleController::permissionGroups()` runs each permission name
  through `translate(Str::headline(...))`, exactly as the Blade form did.

Why not the other two options:

- **A per-page dictionary prop:**
  - every label would depend on a server prop, so Vitest could not assert
    static labels with `t()` (CLAUDE.md asks for every label);
  - each request would call `translate()` once per label, and `translate()`
    also inserts a row for every unseen key at render time.
- **Exporting the `translations` table into vue-i18n at build time:** it
  cannot see edits made in the translation admin after a deploy, and it would
  ship every storefront string to the back office.
- **Why the locale files fit:** back-office copy is developer-owned chrome,
  and WMS already works this way. One mechanism serves both admin UIs.

What it means for the translation admin:

- It (`languages.show`, `app-translations.show`) keeps editing `translations`,
  which still drives the storefront, the seller area, emails, PDFs and the
  mobile API.
- A converted module's strings stop being read from that table. Editing
  "All Staffs" there no longer changes the back office. The rows stay and do
  no harm.
- **Languages:** the back office ships `en` and `ar`.
  - A store language beyond those renders the back office in English, through
    vue-i18n's fallback; the storefront is unaffected.
  - The layout reuses WMS's `LanguageSwitcher`. It writes the same `locale`
    session key that the store's `Language` middleware reads.

### 2.4 Uploads: replacing the aiz file manager (design; built in batch 4)

**Today** (audit §6, product and uploaded_files):

- A form field is a `data-toggle="aizuploader"` group around a hidden input
  that holds comma-separated upload ids.
- Clicking it POSTs `/temp/aiz-uploader`, which returns the modal's HTML.
- The modal uses:
  - `get_uploaded_files` (JSON, 60 per page, own files only);
  - `get_file_by_ids` (previews);
  - `upload` (Uppy).
- `uploaded_asset($id)` and `Upload::url` resolve an id to a URL.

**Storage already normalises** (`MultipartUploadService`):

- The extension comes from the content, not the client.
- Markup is refused, and SVG is sanitised.
- Images are re-encoded with Intervention v2.

Two gaps remain against CLAUDE.md's "re-encode to a fixed format":

- the `disable_image_optimization` setting, and any image over 40 MP, skip the
  re-encode;
- the re-encode keeps each image's own format.

**Design:**

- **Backend.** JSON endpoints under `/temp/admin/uploads`, in
  `Store\Admin\UploadController`. All writes go through
  `MultipartUploadService`.
  - **Endpoints:**
    - `GET` list: paginated, with server-side search, sort and type filter
      (today the type filter runs client-side on one page only);
    - `POST` upload, multipart;
    - `GET ?ids=`: the chosen files' previews;
    - `DELETE`.
  - **Response:** an `UploadResource` of `id, name, type, size, url`.
  - **Permissions:** gate the endpoints with new permission names, and empty
    the `AizUploadController` entries of the ratchet lists.
  - **Normalising images:**
    - re-encode every image to WebP;
    - drop the opt-out;
    - refuse an image over 40 MP instead of storing it raw;
    - fix the 1500 → 800 resize typo (`MultipartUploadService.php:162-166`).
- **Vue.**
  - `components/StoreUploadField.vue`: a form field.
    - It keeps the backend contract, a hidden input `name` with
      comma-separated ids, so product and category forms post what they post
      today.
    - It shows thumbnails, a remove button, and a "Choose" button that opens
      the file manager.
  - `components/StoreFileManagerDialog.vue`:
    - a grid, search, sort, type filter and pagination, over `useHttp`;
    - an upload tab with `<input type="file" multiple>` and a progress bar;
    - no Uppy dependency.
  - **The library page.** `uploaded_files/index` becomes a page around the
    dialog's grid component. The info modal becomes a side panel fed by JSON.
- **Retire** `/temp/aiz-uploader*` with the last Blade view that uses
  aizuploader. The seller area keeps its Blade uploader until the seller UI
  moves, which is out of scope.

Two upload paths are not aiz and stay as they are: the offers catalogue and
bulk import.

### 2.5 Content-Security-Policy

Inertia pages need no inline scripts, so they keep config/secure-headers.php's
strict policy. Unconverted Blade pages need inline scripts.

**How it works:**

- `App\Http\Middleware\SendSecureHeaders` replaces bepsvpt's
  `SecureHeadersMiddleware` as the globally appended middleware. It is
  identical except for one thing: it merges the directives a route middleware
  puts in the `SendSecureHeaders::CSP_OVERRIDE` request attribute.
- `HandleStoreBladePages` sets that attribute to `config('store.blade_csp')`
  for a Blade response only. The Blade policy allows `'self'`,
  `'unsafe-inline'` and `https:` for scripts and styles, and loosens font,
  img, frame and connect the same way.
- A request attribute ends with its request. A `config()` rewrite would leak
  into the next request in the same process, and a test pins this.

**What it changes:**

- Store Inertia pages and WMS pages keep the strict policy.
- Before this change, Blade pages received the strict policy too, which would
  have blocked their scripts once it was enforced.
- CSP stays report-only outside production, as before.
- Shrink `blade_csp` only when the remaining consumers prove a directive is no
  longer needed. Do not delete it with the last back-office page: the retained
  storefront, seller and authentication Blade pages use the same route-wide
  override. Delete it only with the last store Blade page after a separate
  consumer audit.

**Tests:** tests/Feature/Store/HandleStoreBladePagesTest.php.

### 2.6 Coexistence under /temp

Converted and unconverted pages coexist through four mechanisms:

- **Vue to Blade:** a link from a Vue page to a Blade page is a plain
  `<a href>`, i.e. a full load.
  - `StoreAdminNavLink`'s `inertia` flag makes that choice.
  - Wayfinder's `.url()` builds the href, since `@/actions` covers the legacy
    controllers too.
- **Blade to Vue:** a link from a Blade page to a converted page stays an
  ordinary link. Inertia boots from `app.blade.php` on the full load.
- **Route names:** a converted module keeps its route names, so Blade links
  such as `route('staffs.index')` keep working. Its URIs may change, e.g. to
  resource style with bound models.
- **The safety net:** `HandleStoreBladePages` sits on the store's three web
  groups. When an Inertia GET lands on a Blade page, it answers 409 with
  `X-Inertia-Location`, which is Inertia's own "load this in full".
  - This covers a redirect from an Inertia action to an unconverted page.
    Example: logout → the Blade login page, which
    tests/e2e/store-staff-coexistence.spec.ts drives in a browser.
  - It also covers any link wrongly marked Inertia.
  - A response counts as Blade when it renders a view other than Inertia's
    root view, i.e. the view has no `page` data.

## 3. Per-module checklist (the staff pattern)

Do every step for each module, in one change per batch.

1. **Before coding**
   - Read the module's rows in §6 and its findings in §5.1.
   - Confirm the deletes in §5.
   - Check §7.1 of the legacy rule for permission names that don't exist yet.
     A batch with ungated routes adds the names first.
2. **Controller**
   - Create `App\Http\Controllers\Store\Admin\<Name>Controller` with
     `HasMiddleware`, keeping the existing `permission:` names per action.
   - Delete the root-namespace legacy controller.
   - Remove it from both `pint.json` and `phpstan.neon` in the same change
     (`StaticAnalysisExclusionsTest`). A legacy model you clean up comes out
     of both lists too (`Staff`).
3. **Pages and resources**
   - Each page renders `Inertia::render('Store/Admin/<Module>/<Resource>/<Page>')`.
   - **Lists:**
     - take a `Filter<X>Request` using `FiltersPerPage` and
       `resolvePerPage($request, 20)`;
     - query with an explicit `select([...])`, scoped eager loads, `orderBy`
       and `paginate()->withQueryString()`;
     - return `$this->paginated(<X>Resource::collection(...))` plus a
       `filters` prop.
   - **Resources:** in `App\Http\Resources\Store`, documented with
     `@property-read`.
4. **Writes and records**
   - **Validation:** `Store<X>Request`/`Update<X>Request` in
     `App\Http\Requests\Store\Admin`, never inline `validate()`.
     - Rules shared by 2+ requests go in a `Concerns` trait exposing
       `xRules()`.
     - Validate references with `Rule::exists(...)->where('guard_name', 'store')`
       rather than `findOrFail()`, so the response is a 422 with an error, not
       a 404.
   - **Binding:** bind models (`{staff}`) in place of `$id`/`decrypt($id)`.
   - **Record invariants** (Super Admin rows, one's own account): abort 403 in
     the controller. A policy would not work, because `Gate::before()` would
     pass a Super Admin through it.
   - **Redirects:** `redirect()->route('<name>.index')` after a write, and
     `redirectPreservingQuery()` after a delete. No `back()`, no `flash()`.
5. **Routes**
   - Rewrite the module's block of `routes/shop-admin.php` as explicit
     `Route::get/post/put/delete` calls on the new controller.
   - Keep the route names.
   - Drop dead routes: empty `show` methods, missing methods, unused forms.
   - Deletes become `DELETE`. `LegacyStoreStateChangingRoutesTest` accepts
     POST or DELETE.
   - Run `php artisan wayfinder:generate --skip-routes --with-form`.
6. **Vue pages**
   - Put them in `resources/js/pages/Store/Admin/<Module>/<Resource>/`.
   - Wrap them in `StoreAdminLayout`. Forms use `ResourceFormPage` with
     `:layout="StoreAdminLayout"`, plus `wide` for grid forms.
   - **Reuse** `DataTable`, `PageHeader`, `AddResourceLink`, `TableActionLink`,
     `Pagination` (with the per-page selector), `FormField`, `FieldError`,
     `SubmitButton` and `lib/confirm.ts`.
   - Put a module's field group in `components/Store<Resource>FormFields.vue`.
   - Gate buttons with `useStoreAccess().can('<permission>')`, using the same
     names as the middleware.
   - Use Wayfinder actions, with:
     - `index` aliased;
     - model objects as params;
     - `{ mergeQuery: {} }` on deletes.
   - Give every field the native HTML5 constraints that mirror its rules.
7. **Strings and menu**
   - Add `store.<module>.*` keys to both `en.json` and `ar.json`.
   - Flip the module's entries in `lib/storeAdminMenu.ts` to `inertia: true`,
     and update the Inertia list in `storeAdminMenu.test.ts`.
8. **Delete the Blade views** with `git rm`, along with any partial only they
   used.
9. **Pest**
   - Write `tests/Feature/Store/Admin/<Resource>ControllerTest.php`, covering
     per action:
     - a render test;
     - a full-payload Inertia assertion for one record, scoped with closures;
     - validation;
     - 404 for a missing id on edit, update and destroy;
     - the `refused store admin callers` dataset, with a DB-unchanged
       assertion on every write;
     - for lists: more rows than a page, plus an allowed and a refused
       `per_page`.
   - Helpers are in tests/Pest.php: `createStoreStaff(...)`,
     `createStaffRow()`, `actingAsRefusedStoreCaller()`,
     `assertStoreCallerRefused()`.
   - Move the module's existing cases out of the `Legacy*Test` files rather
     than duplicating them.
   - Update the `LegacyAdminPermissionsTest` ratchet lists if a gate changed.
     Staff needed no change: every one of its routes was already gated.
10. **Vitest**
    - Full-mount every page and new component.
    - Assert:
      - the child props (`ResourceFormPage`, the form fields, `TableActionLink`
        hrefs, `Pagination`);
      - every static label;
      - permission-gated buttons, both ways;
      - the `confirmDelete` wiring.
    - Add fixtures to `testing/factories.ts`.
11. **Playwright**
    - Write a `tests/e2e/store-*.spec.ts` for browser-only behaviour: native
      confirms, RTL arrows, dark-mode contrast.
    - `loginAsStoreAdmin()` signs in as the seeded
      `store-admin@example.com` / `password` (`StoreUserSeeder`).
12. **Verify:** run `python3 .claude/scripts/pre_push_check.py`.

**What the staff pilot changed in behaviour.** Keep these deliberate in later
modules too:

- **Routes and URIs**
  - Dead routes are gone: the empty `roles.show` and `staffs.show`,
    `roles.permission` (only a commented-out form used it) and
    `/admin-permissions` (an empty method).
  - URIs are now `/staffs/{staff}/edit`, which was an encrypted id, and
    `DELETE /staffs/{staff}` / `DELETE /roles/{role}`, which were
    `POST …/destroy/{id}`.
- **Super Admin**
  - The Super Admin role can no longer be edited or deleted by URL.
- **Validation**
  - A staff password now needs its confirmation.
  - A wrong role is a 422, not a 404.
  - A role name is limited to 50 characters, the `role_translations.name`
    width, and must be unique among store roles.
  - The `lang` of a role edit must be the default language or a row of
    `languages`.
- **Lists**
  - They page 20 at a time, with a per-page selector; it was 10.
  - Staff rows with no account are left out.
- **Forms**
  - Saving a role returns to the list, not the form.
  - No success flash.
  - The role form never offers the removed addons' permission sections
    (`RoleController::REMOVED_ADDON_SECTIONS`).

**What batch 1 changed in behaviour (verified 2026-10-10).**

- The empty cities create route/view and orphan tax create route/view are
  gone. Currency create/edit are client dialogs, so their AJAX-HTML routes are
  gone as well.
- Edit/update/delete/status routes are explicit and model-bound; deletes use
  `DELETE` and statuses use `PATCH`. Existing route names were retained where
  a live consumer used them.
- The zone list no longer shows the legacy status switch: it called a method
  that never existed. Zone assignment updates countries transactionally.
- Currency codes are required and unique. A default currency cannot be
  disabled. The storefront's separate `/temp/currency` session switcher stays
  in the root `CurrencyController` and has retained-storefront coverage.
- Currency, shipping and order settings no longer pass through the generic
  `business_settings.update` OR gate and no longer run `cache:clear`.
- Order product search now requires `order_configuration`, validates the
  query, returns a narrow JSON shape and is rendered as text by Vue.
- Lists page 20 at a time, preserve filters and expose only Resource fields.
  Translated city and pickup-point edits keep language validation and store
  translations without exposing model timestamps.

**Batch 1 verification (2026-10-10).** The recorded cleanup gate was checked
against the current tree again: 174 back-office Blade files remain and 138
still extend `layouts/app`; only that layout includes `inc/admin_sidenav` and
`inc/admin_nav`. The retained seller/delivery-boy login and 503 templates,
four PDF templates, Inertia root, coexistence middleware and route-wide Blade
CSP still have live consumers. Verification passed as follows:

- full parallel Pest: 2,845 tests, 12,369 assertions;
- full Vitest: 121 files, 1,609 tests; focused batch 1 backend: 95 tests,
  881 assertions; focused batch 1 Vitest: 9 files, 34 tests;
- ESLint, Prettier, `vue-tsc`, Pint (798 files) and PHPStan (467 files);
- production asset build, including Wayfinder generation;
- the three store coexistence Playwright flows: staff creation/deletion,
  Inertia-to-Blade navigation plus logout, and direct country/tax visits with
  destructive confirmation. The local run used the built assets because a
  stale ignored `public/hot` marker pointed at a stopped Vite server.

The full PHP run also exposed and fixed the retained representative-payment
PDF's XML-declaration stripping: the literal `<?xml ... ?>` inside a Blade raw
echo prevented Blade from compiling the template. It now removes the trusted
QR SVG declaration with a regular expression and the retained PDF regression
file passes. This does not change the decision to keep the template in Blade.

## 4. Migration batches

The request that set up this migration had no batch list attached, so these
batches come from the audit alone. Each batch is one session's change: one
push and one CI round.

Batches are ordered by what they need first:

- no new UI piece;
- then the upload field (§2.4);
- then a rich-text editor and maps, both new npm dependencies that need the
  owner's approval (CLAUDE.md);
- the orders screens last, because they are the riskiest.

| # | Batch | Views | Modules (views) | Needs first |
| --- | --- | --- | --- | --- |
| 0 | Staff (pilot) | 6 | staff (6) | done |
| 1 | Shipping and tax reference data | 20 | setup_configurations: countries (1), states (2), cities (3, one deleted), zones (3), pickup_point (3), tax (3, one deleted), shipping_configuration (1), order_configuration (1), currencies (3) | **done 2026-10-10.** Currency create/edit are dialogs; product search is permission-gated JSON rendered as Vue text; currency, shipping and order settings use dedicated allowlisted endpoints and targeted cache invalidation |
| 2 | Languages, translation admin and plain settings | 15 | setup_configurations: languages (5), activation, smtp_settings, social_login, facebook_chat, facebook_comment, google_analytics, google_recaptcha, google_firebase, payment_method, file_system | Per-page settings endpoints with an allowlist, replacing `business_settings.update`'s OR gate (§5.1). `.env`-only fields become read-only or are dropped. No `artisan down`, no `cache:clear` |
| 3 | Reports and notifications | 11 | reports (8, partial folded in), notification (1), customer/notifications (2) | none. `DateRangeFilterFields` and `FilterMultiSelect` already exist |
| 4 | Uploads | 4 | uploaded_files (3), admin_profile (1) | §2.4 |
| 5 | Customers and representatives | 19 | customer (11: customers, classified products, customer packages), representatives (8: index, create, packages ×4, payments, visits index) | upload field. Permission names for rep packages, payments and visits (legacy §7.1). Admin-side address endpoints |
| 6 | Sellers | 13 | sellers (13) | Owner confirms multi-vendor (`vendor_system_activation`) is in use. If not, delete instead |
| 7 | Catalogue | 27 | product (22), setup_configurations: general_settings (1), carriers (3); offers_catalog (1) | upload field; a rich-text editor, a tag input and a client-side variant grid; a permission name for the offers catalogue |
| 8 | Marketing and blog | 31 | marketing (25, two deleted), blog_system (6) | upload field and editor; offer and flash product rows as client-side grids |
| 9 | Website setup and support | 13 | website_settings (7), support (6) | upload field and editor; per-page settings endpoints; support replies keep server-side `clean_html()` |
| 10 | Logistics with maps | 22 | trips (19, one deleted), representatives visits show and rep-show (2), setup_configurations: google_map (1) | Leaflet (a new dependency); permission names; the trips controllers leave `ControllerRedirectsTest`'s exclusions |
| 11 | Orders and dashboard | 10 | sales (9, four deleted), dashboard (1) | a shared order-entry component with async search; a chart library (a new dependency); permission names for order entry |
| 12 | Shell and leftovers | 10 originally (9 remain) | layouts (3; blank deleted), inc (2), system (1), invoices (1), downloads (3) | Last: delete only `layouts/app` and its includes after the last Blade module; keep `layouts/layout` and the PDF views (§5), resolve server status with the owner, then re-audit the route-wide CSP against every retained store Blade page |

Totals: 6+20+15+11+4+19+13+27+31+13+22+10+10 = 201.

## 5. Views to delete rather than port

| View | Why |
| --- | --- |
| setup_configurations/cities/create | **Deleted in batch 1.** 0-byte file; `CityController@create` was empty |
| setup_configurations/tax/create | **Deleted in batch 1.** Nothing rendered it; a byte copy of pickup_point/create |
| marketing/offer_deals/brands/index | Nothing renders it; a duplicate of offer_deals/index |
| marketing/offer_deals/brands/offer_deal_discount | Nothing renders it. Its route `offer_deals.brand_discount` points at a missing method: delete the route too |
| sales/new-order | Nothing links to `feat-order.trip.create`; new-shipping-order replaced it. Delete routes shop-admin.php :435, :440-442 and :450 with it |
| sales/user-data-table | Its only caller is new-order |
| sales/products-data-repeater | Nothing renders it |
| sales/products-data-table | Nothing renders it. Its route is commented out and its method is missing |
| trips/inc/styles | Only included inside `@section('style')`, which the layout never yields |
| layouts/blank | No view extends it (leftover installer CSS) |
| system/server_status | Owner call. `/health` supersedes it; its PHP "7.3/7.4" check is always red and its `select version()` is MySQL-only |

**Ported nowhere, kept as server-rendered PDFs:** invoices/invoice and
downloads/brand, category and user (mPDF). They move with phase 6 (§1 of the
legacy rule plans dompdf + ar-php). `layouts/layout` stays as well: it serves
the storefront's seller login, not the back office.

### 5.1 Findings to fix with (or before) their batch

Most severe first:

1. **ProfileController@update** (admin_profile) took the id from the URL
   with no ownership check, so any staff member could rewrite any account's
   name, email, password or avatar. **Fixed**: `AdminProfileRequest`
   (`authorize()` compares the `profile` route parameter with the signed-in
   store user, so a foreign id is a 403 before validation) validates name,
   a unique email, an optional strong `new_password` (`same:confirm_password`)
   and an existing single-id `avatar` (the uploader has no `data-multiple`);
   the controller updates `$request->user('store')`. The five empty resource
   stubs are gone (`->only(['index', 'update'])`). Tests:
   `LegacyAdminProfileTest`.
2. **`business_settings.update`** accepts any one of eight permissions and
   upserts any key with no allowlist. A user holding only `currency_setup` can
   write `header_script`/`footer_script`, which the storefront prints raw
   (stored XSS).
   - **Batch 1 fixed:** currency formats/defaults, shipping configuration and
     order configuration now use separate permission-gated controllers,
     request allowlists and `Cache::forget('business_settings')`; their old
     generic methods were removed from `BusinessSettingsController`.
   - Batches 2 and 9 replace it with one endpoint per page, each with that
     page's permission and its own keys.
3. **Unescaped HTML built from data:**
   - **Fixed in batch 1:** `order_configuration.product-search` returns only
     `id`, `name` and `mat_id` as permission-gated JSON; Vue renders names as
     text instead of concatenating HTML;
   - `products.add-more-choice-option` returns unescaped `<option>` values
     (batch 7).
4. **Write gates that don't match the buttons:**
   - `CustomerController` writes behind the read permission
     `view_all_customers`;
   - ticket, product-query and conversation replies are checked in the view
     only;
   - `approve_seller` exists nowhere;
   - the store routes outside `temp/admin` (`customers.sendAgreement`,
     `addresses.*`) are invisible to the permission ratchet.
   - The port gates the endpoint, not the button.
5. **Settings that affect the whole merged app:**
   - every settings save runs `cache:clear` on the cache WMS shares;
   - activation's `maintenance_mode` runs `artisan down`, which takes WMS down
     too.
   - Neither is ported.
6. **Links and redirects that drop `/temp`:**
   - blog category links;
   - the flash deal page link;
   - `BlogController.php:175` and `BlogCategoryController.php:146`;
   - the digital-product redirects in ProductController;
   - the aiz uploader's `appUrl`. Check that one in a browser while Blade
     modules still use it.
7. **Route names that went to storefront routes:** `reviews.index` and
   `subscribers.index`. Give the admin routes their own names when their
   batch converts. The new sidebar already links to the admin URIs.
8. **N+1 and strict mode.** `Model::shouldBeStrict()` throws on a lazy load
   once a list has two or more rows, and no list view in sales, reports,
   customer or sellers has a render test. Each port eager-loads what its
   Resource reads.

## 6. Audit, per view

The columns are as the request asked:

- **Route name · verb URI:** the line numbers are in `routes/shop-admin.php`
  unless another file is named.
- **Controller@method:** the number is the line that renders the view.
- **Permission:**
  - "ungated" means the action is listed in `LegacyAdminPermissionsTest`'s
    ratchet lists;
  - every route under `/temp/admin` also runs `auth` and `admin`.
- **Tests:** what renders or exercises the view. The 403 sweep
  (`LegacyAdminPermissionsTest.php:185`) reaches every gated route but renders
  nothing, so it is not listed.

The per-module tables were built by Explore agents from static reads, cited by
line, and spot-checked. Re-check a row against the code before relying on it
for a batch.

### staff (6 views): converted in the pilot

| View | Route name · verb URI | Now | Permission | Kind | JS it depended on | Tests now | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- |
| staff/staffs/index | `staffs.index` · GET /temp/admin/staffs | Store\Admin\StaffController@index → `Store/Admin/Staff/Staffs/Index` | `view_all_staffs` | list | confirm-delete modal, data-method post | tests/Feature/Store/Admin/StaffControllerTest.php; Index.test.ts; e2e store-staff-coexistence.spec.ts | converted, Blade deleted |
| staff/staffs/create | `staffs.create` · GET …/staffs/create; `staffs.store` · POST …/staffs | @create, @store → `…/Staffs/Create` | `add_staff` | form | aiz-selectpicker | same; Create.test.ts | converted |
| staff/staffs/edit | `staffs.edit` · GET …/staffs/{staff}/edit; `staffs.update` · PUT …/staffs/{staff}; `staffs.destroy` · DELETE …/staffs/{staff} | @edit, @update, @destroy → `…/Staffs/Edit` | `edit_staff`, `delete_staff` | form | aiz-selectpicker | same; Edit.test.ts | converted |
| staff/staff_roles/index | `roles.index` · GET /temp/admin/roles; `roles.destroy` · DELETE …/roles/{role} | Store\Admin\RoleController@index, @destroy → `Store/Admin/Staff/Roles/Index` | `view_staff_roles`, `delete_staff_role` | list | confirm-delete modal | tests/Feature/Store/Admin/RoleControllerTest.php; Index.test.ts | converted |
| staff/staff_roles/create | `roles.create`, `roles.store` | @create, @store → `…/Roles/Create` | `add_staff_role` | form (permission grid) | aiz-switch checkboxes | same; Create.test.ts | converted |
| staff/staff_roles/edit | `roles.edit` · GET …/roles/{role}/edit?lang=; `roles.update` · PUT …/roles/{role} | @edit, @update → `…/Roles/Edit` | `edit_staff_role` | form (language tabs + permission grid) | aiz-switch checkboxes | same; Edit.test.ts | converted |

### setup_configurations (40 views) and downloads (3 views)

URIs are under `/temp/admin` unless shown. `s/c/` = `setup_configurations/`, BSC = BusinessSettingsController. Every gated route here is also hit by the 403 sweep (`LegacyAdminPermissionsTest.php:185`), which never renders a page; "none" below means no test renders the view.

| View | Route name · verb URI (route line) | Controller@method (render line) | Permission | Kind | JS it depends on | Tests | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- |
| downloads/brand | `pdf.download_brand`, `seller.pdf.download_brand` · GET …/bulk-upload/download/brand (:149; shop-seller:43) | ProductBulkUploadController@pdf_download_brand (:67), Seller\… (:39) | `product_bulk_import`; seller: role middleware | print (mPDF) | none | none | keep Blade (PDF download, shared with the seller area) |
| downloads/category | `pdf.download_category`, `seller.pdf.download_category` (:148; shop-seller:42) | ProductBulkUploadController@pdf_download_category (:58), Seller\… (:30) | `product_bulk_import` | print (mPDF) | none | none | keep Blade (PDF download) |
| downloads/user | `pdf.download_seller` · GET …/bulk-upload/download/seller (:150) | ProductBulkUploadController@pdf_download_seller (:76) | `product_bulk_import` | print (mPDF) | none | none | keep Blade (PDF); nothing links to it |
| s/c/activation | `activation.index` · GET …/activation (:315) | BSC@activation (:54) | `features_activation` + `RaizerSuperAdmin` | form (toggle board) | `$.post business_settings.update.activation` (returns 1/0), notify | LegacyAdminPanelTest:152,158; LegacyEnvFileProtectionTest:77 | port-trim: wholesale/auction cards (194-223), ProxyPay card (734-752, no controller), FORCE_HTTPS card (7-19, `.env` only); do not port `maintenance_mode` → `artisan down` (takes WMS down too) |
| s/c/carriers/create | `carriers.create` (:625) | CarrierController@create (:50) | `manage_carriers` | form | aizuploader, aiz-selectpicker, range-table cloning, `$.ajax` FormData → `carriers.store` (1 or 422 JSON) | none | port |
| s/c/carriers/edit | `carriers.edit` (:625) | CarrierController@edit (:106) | `manage_carriers` | form | as create, `$.ajax` → `carriers.update` | none | port |
| s/c/carriers/index | `carriers.index` (:625) | CarrierController@index (:39) | `manage_carriers` | list | `$.post carriers.update_status`, confirm-delete | LegacyStoreStateChangingRoutesTest:117 | port |
| s/c/cities/create | dead route removed | — | `manage_shipping_cities` | — (0-byte file) | — | CityControllerTest | **deleted in batch 1** |
| s/c/cities/edit | `cities.edit` · GET …/cities/{city}/edit; update PUT and destroy DELETE …/cities/{city} | Store\Admin\CityController → `Store/Admin/Shipping/Cities/Edit` | `manage_shipping_cities` | form (language tabs) | shared Vue fields | CityControllerTest; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/cities/index | `cities.index` · GET …/cities; store POST; status PATCH …/{city}/status | Store\Admin\CityController → `Store/Admin/Shipping/Cities/Index` | `manage_shipping_cities` | paginated list + inline add form | Inertia forms/status/delete | CityControllerTest; StoreCityFormFields.test.ts; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/countries/index | `countries.index` · GET …/countries; status PATCH …/{country}/status | Store\Admin\CountryController → `Store/Admin/Shipping/Countries/Index` | `shipping_country_setting` | paginated list | Inertia status form | CountryControllerTest; StoreReferenceStatusForm.test.ts; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/currencies/create | folded into `currency.index` | Store\Admin\CurrencyController@index → create dialog | `currency_setup` | dialog | Vue form; no AJAX HTML injection | CurrencyControllerTest; StoreCurrencyFormFields.test.ts; BatchOnePages.test.ts | converted, Blade and dead route deleted |
| s/c/currencies/edit | folded into `currency.index`; update PUT …/currency/{currency} | Store\Admin\CurrencyController@index → edit dialog | `currency_setup` | dialog | Vue form; no AJAX HTML injection | same | converted, Blade and dead route deleted |
| s/c/currencies/index | `currency.index` · GET/POST …/currency; default/formats PUT; status PATCH | Store\Admin\CurrencyController → `Store/Admin/Setup/Currencies/Index` | `currency_setup` | paginated list + dialogs + settings forms | dedicated allowlisted endpoints | CurrencyControllerTest; StoreCurrencyFormFields.test.ts; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/facebook_chat | `facebook_chat.index` (:326) | BSC@facebook_chat (:87) | `facebook_chat` | form | none | none | port-trim: the page-id field writes `.env` (refused); only the toggle saves |
| s/c/facebook_configuration/facebook_comment | `facebook-comment` (:328) | BSC@facebook_comment (:92) | `facebook_comment` | form | none | none | port-trim: app-id `.env` field does nothing |
| s/c/file_system | `file_system.index` (:317) | BSC@file_system (:102) | `file_system_&_cache_configuration` | form | aiz-selectpicker, `$.post …update.activation` | LegacyEnvFileProtectionTest:38 | port-trim: every input is `.env` (refused) and its forms post to `payment_method.update`, resetting `paypal_sandbox`; read-only status at most, stop printing secrets |
| s/c/general_settings | `general_setting.index` (:314) | BSC@general_setting (:49) | `general_settings` | form | aizuploader ×3, aiz-selectpicker | LegacyAdminPermissionsTest:228 (write endpoint) | port-trim: timezone is `.env` only |
| s/c/google_configuration/google_analytics | `google_analytics.index` (:320) | BSC@google_analytics (:69) | `analytics_tools_configuration` | form | none | none | port-trim: pixel/tracking-id `.env` fields do nothing |
| s/c/google_configuration/google_firebase | `google-firebase.index` (:323) | BSC@google_firebase (:82) | `google_firebase_setting` | form | none | none | port-trim: `FCM_SERVER_KEY` field does nothing |
| s/c/google_configuration/google_map | `google-map.index` (:322) | BSC@google_map (:78) | `google_map_setting` | form + map picker | Google Maps JS (places) via `shop.frontend.partials.google_map` | none | port-trim: `MAP_API_KEY` field does nothing |
| s/c/google_configuration/google_recaptcha | `google_recaptcha.index` (:321) | BSC@google_recaptcha (:74) | `google_recaptcha_configuration` | form | none | none | port-trim: key field does nothing |
| s/c/languages/app_translation | `app-translations.show` (:384) | LanguageController@showAppTranlsationView (:226) | `language_setup` | form (key grid) | inline copyTranslation() | none | port |
| s/c/languages/create | `languages.create` (:374) | LanguageController@create (:51) | `language_setup` | form | aiz-selectpicker (flags) | none | port |
| s/c/languages/edit | `languages.edit` (:374) | LanguageController@edit (:91) | `language_setup` | form | aiz-selectpicker | RouteServiceProviderTest:43 | port |
| s/c/languages/index | `languages.index` (:374) | LanguageController@index (:46) | `language_setup` | list + 2 forms | `$.post languages.update_rtl_status`/`update-status`, confirm-delete | LegacyTripUploadTest:43; LegacyEnvFileProtectionTest:50 | port-trim: Default Language form posts to `env_key_update` (refused) |
| s/c/languages/language_view | `languages.show` (:374) | LanguageController@show (:85) | `language_setup` | form (key grid) | inline copyTranslation() | none | port — this is the translation admin; see §2.3 |
| s/c/order_configuration/index | `order_configuration.index` · GET …/order-configuration; two PUT settings endpoints; GET …/product/search | Store\Admin\OrderConfigurationController → `Store/Admin/Setup/OrderConfiguration/Index` | `order_configuration` | form | permission-gated JSON search; Vue text rendering | ConfigurationControllersTest; BatchOnePages.test.ts | converted, Blade deleted; XSS sink removed |
| s/c/payment_method | `payment_method.index` (:316) | BSC@payment_method (:97) | `payment_methods_configurations` | form (8 gateway cards) | none | none | port-trim: credential inputs are `.env` (do nothing), Authorize.Net stub; only `<gw>_sandbox` toggles save; gateways pending phase 0b |
| s/c/pickup_point/create | `pick_up_points.create` · GET …/pick_up_points/create; store POST | Store\Admin\PickupPointController → `Store/Admin/Setup/PickupPoints/Create` | `pickup_point_setup` | form | shared Vue fields | PickupPointControllerTest; StorePickupPointFormFields.test.ts; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/pickup_point/edit | `pick_up_points.edit` · GET …/pick_up_points/{pickupPoint}/edit; update PUT | Store\Admin\PickupPointController → `Store/Admin/Setup/PickupPoints/Edit` | `pickup_point_setup` | form (language tabs) | shared Vue fields | same | converted, Blade deleted |
| s/c/pickup_point/index | `pick_up_points.index` · GET; destroy DELETE …/{pickupPoint} | Store\Admin\PickupPointController → `Store/Admin/Setup/PickupPoints/Index` | `pickup_point_setup` | paginated list | native confirm through shared table action | same; focused Playwright spec | converted, Blade deleted |
| s/c/shipping_configuration/index | `shipping_configuration.index` · GET; update POST | Store\Admin\ShippingConfigurationController → `Store/Admin/Shipping/Configuration/Index` | `shipping_configuration` | form | dedicated allowlisted endpoint | ConfigurationControllersTest; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/smtp_settings | `smtp_settings.index` (:319) | BSC@smtp_settings (:64) | `smtp_settings` | form | aiz-selectpicker, inline checkMailDriver() | LegacyEnvFileProtectionTest:50 | port-trim: whole form posts to `env_key_update` (refused) — read-only; keep "Send test email" (`test.smtp`, gated by `send_newsletter`) |
| s/c/social_login | `social_login.index` (:318) | BSC@social_login (:59) | `social_media_logins` | form | none | LegacyEnvFileProtectionTest:50 | port-trim: all 4 forms post to `env_key_update` — read-only or drop |
| s/c/states/edit | `states.edit` · GET …/states/{state}/edit; update PUT and destroy DELETE …/{state} | Store\Admin\StateController → `Store/Admin/Shipping/States/Edit` | `manage_shipping_states` | form | shared Vue fields | StateControllerTest; StoreStateFormFields.test.ts; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/states/index | `states.index` · GET/POST …/states; status PATCH …/{state}/status | Store\Admin\StateController → `Store/Admin/Shipping/States/Index` | `manage_shipping_states` | paginated list + inline add form | Inertia forms/status/delete | same | converted, Blade deleted |
| s/c/tax/create | dead route removed | — | `vat_&_tax_setup` | — (orphan) | — | TaxControllerTest | **deleted in batch 1** |
| s/c/tax/edit | `tax.edit` · GET …/tax/{tax}/edit; update PUT and destroy DELETE …/{tax} | Store\Admin\TaxController → `Store/Admin/Setup/Taxes/Edit` | `vat_&_tax_setup` | form | shared Vue fields | TaxControllerTest; StoreTaxFormFields.test.ts; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/tax/index | `tax.index` · GET/POST …/tax; status PATCH …/{tax}/status | Store\Admin\TaxController → `Store/Admin/Setup/Taxes/Index` | `vat_&_tax_setup` | paginated list + inline add | Inertia forms/status/delete | same; focused Playwright spec | converted, Blade deleted |
| s/c/zones/create | `zones.create` · GET …/zones/create; store POST | Store\Admin\ZoneController → `Store/Admin/Shipping/Zones/Create` | `manage_zones` | form | shared Vue fields | ZoneControllerTest; StoreZoneFormFields.test.ts; BatchOnePages.test.ts | converted, Blade deleted |
| s/c/zones/edit | `zones.edit` · GET …/zones/{zone}/edit; update PUT and destroy DELETE …/{zone} | Store\Admin\ZoneController → `Store/Admin/Shipping/Zones/Edit` | `manage_zones` | form | shared Vue fields | same | converted, Blade deleted |
| s/c/zones/index | `zones.index` · GET …/zones | Store\Admin\ZoneController → `Store/Admin/Shipping/Zones/Index` | `manage_zones` | paginated list | native confirm; broken legacy status toggle deliberately dropped | same | converted, Blade deleted |

Notes:
1. `env_key_update`, `overWriteEnvFile` and `updateActivationSettingsInEnv` refuse every request (BSC.php:320-358, 483-488), so smtp_settings, social_login, file_system and the Default Language form save nothing, and the google_*/facebook_*/payment_method screens save only their toggles. These views print `env()` secrets into `value=` attributes (`NoEnvOutsideConfigTest.php:58` exempts this folder); the port reads `config()` or masks them.
2. Retained generic settings saves still run `Artisan::call('cache:clear')`, which clears the cache WMS shares. Batch 1's currency, shipping and order settings no longer use it; later ports must also forget only their specific cache keys.
3. Batch 1 removed currencies/index's AJAX HTML create/edit endpoints; no converted page injects server HTML.
4. Batch 1 resolved the duplicate `tax.edit`, `cities.edit`, `pick_up_points.edit` and destroy routes into explicit model-bound routes. Remaining duplicates (`languages.update` and unconverted `*.destroy`) stay for their batches.

### marketing (25 views), blog_system (6), notification (1)

URIs are under `/temp/admin`. Every gated route is also hit by the 403 sweep; SCR = `LegacyStoreStateChangingRoutesTest` (verb list, plus the `:183` scan for `data-method="post"` on delete links).

| View | Route name · verb URI (route line) | Controller@method (render line) | Permission | Kind | JS it depends on | Tests | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- |
| blog_system/blog/create | `blog.create` (:533) | BlogController@create (:61) | `add_blog` | form | aiz-selectpicker, aizuploader ×2, summernote, makeSlug() | none | port |
| blog_system/blog/edit | `blog.edit` (:533) | BlogController@edit (:120) | `edit_blog` | form | as create | none | port (`Blog::find` without 404) |
| blog_system/blog/index | `blog.index` (:533) | BlogController@index (:50) | `view_blogs` | list | `$.post blog.change-status` (`publish_blog`), confirm-delete | SCR:32 | port |
| blog_system/category/create | `blog-category.create` (:529) | BlogCategoryController@create (:58) | `add_blog_category` | form | none | none | port |
| blog_system/category/edit | `blog-category.edit` (:529) | BlogCategoryController@edit (:108) | `edit_blog_category` | form | none | none | port |
| blog_system/category/index | `blog-category.index` (:529) | BlogCategoryController@index (:47) | `view_blog_categories` | list | confirm-delete | SCR:31 | port (its links use `url('admin/blog-category/…')`, which misses `/temp`) |
| marketing/coupons/create | `coupon.create` (:540) | CouponController@create (:51) | `add_coupon` | form | aiz-selectpicker; `$.post coupon.get_coupon_form` → `.html(data)` | none | port |
| marketing/coupons/edit | `coupon.edit` (:540) | CouponController@edit (:90) | `edit_coupon` | form | `$.post coupon.get_coupon_form_edit` → `.html(data)` on load | none | port |
| marketing/coupons/index | `coupon.index` (:540) | CouponController@index (:41) | `view_all_coupons` | list (not paginated) | confirm-delete | SCR:42 | port |
| marketing/dynamic_popup/create | `dynamic-popups.create` (:301) | DynamicPopupController@create (:58) | `add_dynamic_popups` | form | aizuploader, aiz-selectpicker ×2 (queries in the view) | LegacyDynamicPopupTest:39 (store) | port |
| marketing/dynamic_popup/edit | `dynamic-popups.edit` (:301) | DynamicPopupController@edit (:93) | `edit_dynamic_popups` | form | as create | LegacyDynamicPopupTest:60 (update) | port |
| marketing/dynamic_popup/index | `dynamic-popups.index` (:301) | DynamicPopupController@index (:48) | `view_all_dynamic_popups` | list | confirm-delete, bulk-delete `$.ajax`, `$.post dynamic-popups.update-status` | SCR:57 | port |
| marketing/flash_deals/create | `flash_deals.create` (:422) | FlashDealController@create (:59) | `add_flash_deal` | form | aizuploader, aiz-date-range, aiz-selectpicker ×2; `$.post flash_deals.product_discount` → `.html(data)` | none | port |
| marketing/flash_deals/edit | `flash_deals.edit` · GET …/flash_deals/edit/{id}?lang= (:424) | FlashDealController@edit (:130) | `edit_flash_deal` | form (language tabs) | as create; `…product_discount_edit` → `.html(data)` | none | port |
| marketing/flash_deals/flash_deal_discount | `flash_deals.product_discount` · POST (:428) | FlashDealController@product_discount (:236) | `add_flash_deal` | partial (AJAX HTML) | — | none | port as client-side rows |
| marketing/flash_deals/flash_deal_discount_edit | `flash_deals.product_discount_edit` · POST (:429) | FlashDealController@product_discount_edit (:242) | `edit_flash_deal` | partial (AJAX HTML) | — | none | port as client-side rows |
| marketing/flash_deals/index | `flash_deals.index` (:422) | FlashDealController@index (:49) | `view_all_flash_deals` | list | `$.post flash_deals.update_status`/`update_featured` | LegacyPaginationTest:220 (renders) | port (page link `url('flash-deal/'.slug)` misses `/temp`) |
| marketing/newsletters/index | `newsletters.index` (:295) | NewsletterController@index (:33) | `send_newsletter` | form | aiz-selectpicker ×2 (every user email), summernote | none | port (replace the all-emails select with search) |
| marketing/offer_deals/base_offer_deal_discount | `offer_deals.base_product_discount` · GET (:466) | OfferController@base_product_discount (:168) | `add_offer_deal` | partial (AJAX HTML) | — | none | port as client-side rows |
| marketing/offer_deals/brands/base_offer_deal_discount | `offer_deals.base_brand_discount` · GET (:471) | OfferController@base_brand_discount (:180) | `add_offer_deal` | partial (AJAX HTML) | — | none | port as client-side rows |
| marketing/offer_deals/brands/create | `offer_deals.brands.create` (:470) | OfferController@brandCreate (:192) | `add_offer_deal` | form | aizuploader, aiz-date-range, selectpickers, two `.html(data)` loads | SCR:63 | port |
| marketing/offer_deals/brands/edit | `offer_deals.edit` (brand offers) (:459) | OfferController@edit (:59) | `edit_offer_deal` | form | as create; `…brand_discount_edit` → `.html(data)` | none | port (no `brand_offer` input today, so saving recreates the base row with a null product) |
| marketing/offer_deals/brands/index | — | none | — | list | — | none | **delete** (nothing renders it; duplicate of offer_deals/index) |
| marketing/offer_deals/brands/offer_deal_discount | `offer_deals.brand_discount` · GET (:472) | OfferController@brand_discount — **missing** | `add_offer_deal` | partial | — | none | **delete**, with its route |
| marketing/offer_deals/brands/offer_deal_discount_edit | `offer_deals.brand_discount_edit` · POST (:473) | OfferController@brand_discount_edit (:187) | `edit_offer_deal` | partial (AJAX HTML) | — | none | port as client-side rows |
| marketing/offer_deals/create | `offer_deals.create` (:457) | OfferController@create (:47) | `add_offer_deal` | form | aizuploader, aiz-date-range, selectpickers, two `.html(data)` loads | SCR:63 | port |
| marketing/offer_deals/edit | `offer_deals.edit` (product offers) (:459) | OfferController@edit (:61) | `edit_offer_deal` | form | `…product_discount_edit` → `.html(data)` | none | port |
| marketing/offer_deals/index | `offer_deals.index` (:456) | OfferController@index (:42) | `view_all_offer_deals` | list | `$.post` status/repeatable/multiple-usage toggles | LegacyAdminPermissionsTest:260; SCR:62 | port |
| marketing/offer_deals/offer_deal_discount | `offer_deals.product_discount` · GET (:467) | OfferController@product_discount (:164) | `add_offer_deal` | partial (AJAX HTML) | — | none | port as client-side rows |
| marketing/offer_deals/offer_deal_discount_edit | `offer_deals.product_discount_edit` · POST (:468) | OfferController@product_discount_edit (:175) | `edit_offer_deal` | partial (AJAX HTML) | — | none | port as client-side rows |
| marketing/subscribers/index | admin route unnamed (:478); the name `subscribers.index` went to the storefront's GET /temp/subscribers (shop-web:245) | SubscriberController@index (:36) | `view_all_subscribers` | list | confirm-delete | SCR:84 | port; give the admin route its own name |
| notification/index | `admin.all-notification` (:654) | NotificationController@index (:16) | ungated (READS list) | list (15/page) | none | none | port; the GET marks every unread notification as read |

Notes:
1. AJAX endpoints that return HTML (all injected with `.html(data)`): `coupon.get_coupon_form[_edit]` (returning `shop.partials.coupons.*`, outside this folder, with their own inline scripts), `flash_deals.product_discount[_edit]`, `offer_deals.base_product_discount`, `product_discount`, `base_brand_discount`, `product_discount_edit`, `brand_discount_edit`. The partials only render product name, thumbnail and price plus inputs: client-side rows fed by props.
2. The sidebar only shows these screens when `raizer_admin` is set or a business setting is on (`Blog_Section`, `Marketing_Section`, `Flash_deals`, `Offer_deals`, `Newsletters`, `coupon_system`); the controllers never check those settings.
3. Hardcoded URLs without `/temp`: blog category links, the flash deal page link, `BlogController.php:175` (`redirect('admin/blogs')`), `BlogCategoryController.php:146`.
4. No FCM sending here: that is `AdminNotificationController`, rendering `customer/notifications/*`.

### product (22 views), uploaded_files (3), offers_catalog (1)

URIs are under `/temp/admin` unless shown. SCR = `LegacyStoreStateChangingRoutesTest` (verb list only, never renders).

| View | Route name · verb URI (route line) | Controller@method (render line) | Permission | Kind | JS it depends on | Tests | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- |
| offers_catalog/index | `offers.index` · GET …/offers/index (:124) | OffersCatalogController@index (:21) | ungated (READS list) | list + inline PDF upload (plain file input, `OffersCatalogStorage`, not aiz) | confirm-delete | LegacyOffersCatalogTest (store/download/destroy, not this page) | port; needs a permission name (§7.1) |
| product/attribute/attribute_value/edit | `edit-attribute-value` (:590) | AttributeController@edit_attribute_value (:166) | `edit_product_attribute_value` | form | none | none | port |
| product/attribute/attribute_value/index | `attributes.show` (:583) | AttributeController@show (:93) | `view_product_attribute_values` | list + inline create | confirm-delete | SCR | port |
| product/attribute/edit | `attributes.edit` · GET …/attributes/edit/{id} (:585) | AttributeController@edit (:106) | `edit_product_attribute` | form (language tabs) | none | none | port |
| product/attribute/index | `attributes.index` (:583) | AttributeController@index (:47) | `view_product_attributes` | list + inline create | confirm-delete | SCR | port |
| product/brand_wise_discount/set_discount | `brands_wise_product_discount` (:91) | BrandController@brandsWiseProductDiscount (:175) | `view_all_brands` (its write needs `product_edit`) | list, a form per row | aiz-date-range, `$.post set_product_discount` | none | port |
| product/brands/edit | `brands.edit` · GET …/brands/edit/{id} (:87) | BrandController@edit (:111) | `edit_brand` | form | aizuploader ×2 | RouteServiceProviderTest:46 (name) | port |
| product/brands/index | `brands.index` (:85) | BrandController@index (:46) | `view_all_brands` (store: `add_brand`) | list + inline create | aizuploader, confirm-delete | **LegacyAdminPanelTest:66-76 (renders)**; LegacyAdminPermissionsTest:212 | port |
| product/bulk_upload/index | `product_bulk_upload.index` (:143) | ProductBulkUploadController@index (:46) | `product_bulk_import` | form (plain file input) | none | none | port |
| product/categories/create | `categories.create` (:77) | CategoryController@create (:63) | `add_product_category` | form | aiz-selectpicker (live search), aizuploader ×3, `shop.categories.child_category` | none | port |
| product/categories/edit | `categories.edit` · GET …/categories/edit/{id} (:79) | CategoryController@edit (:143) | `edit_product_category` | form | as create | none | port |
| product/categories/index | `categories.index` (:77) | CategoryController@index (:49) | `view_product_categories` | list | `$.post categories.featured`, confirm-delete | RouteServiceProviderTest:47 (name); SCR | port |
| product/color/edit | `colors.edit` · GET …/colors/edit/{id} (:597) | AttributeController@edit_color (:223) | `edit_color` | form | none | none | port |
| product/color/index | `colors` (:595) | AttributeController@colors (:202) | `view_colors` | list + inline create | color-filter switch posts to `business_settings.update.activation` (RaizerSuperAdmin only) | SCR | port-trim: move the switch to settings |
| product/digital_products/create | `digitalproducts.create` (:131) | DigitalProductController@create (:66) | `add_digital_product` | form | aizuploader ×4 (one any-type file), selectpicker, summernote, aiz-tag-input, aiz-date-range | none | port |
| product/digital_products/edit | `digitalproducts.edit` · GET …/edit/{id} (:133) | DigitalProductController@edit (:164) | `edit_digital_product` | form | as create | none | port |
| product/digital_products/index | `digitalproducts.index` (:131) | DigitalProductController@index (:52) | `show_digital_products` | list | 3 switches posting to `products.*` (need `product_edit`) | SCR | port |
| product/products/create | `products.create` (:99) | ProductController@create (:270) | `add_new_product` | form | selectpicker ×10, switches ×22, aizuploader ×4, summernote, aiz-tag-input, aiz-date-range; `$.ajax add-more-choice-option` (`<option>` strings) and `sku_combination` → `.html(data)` | none | port-trim: pos barcode (:92), refund (:101), club point (:306) blocks |
| product/products/edit | `products.admin.edit`, `products.seller.edit` · GET …/products/{admin\|seller}/{id}/edit (:101-102) | ProductController@admin_product_edit (:421), @seller_product_edit (:445) | `product_edit` | form | as create + tagify; `sku_combination_edit` → `.html(data)` | none | port-trim: same addon blocks; `seller_product_edit` passes no `$variations`/`$barcodes`, so the page errors |
| product/products/index | `products.admin`, `products.seller`, `products.all` (:96-98) | ProductController@admin_products (:119), seller_products (:161), all_products (:253) | `show_in_house_products` / `show_seller_products` / `show_all_products` | list | switches ×20, 5 `$.post` toggles, bulk delete, `products.fetch` (JSON) | SCR | port |
| product/products/sku_combinations | `products.sku_combination` · POST (:117); also the seller's | ProductController@sku_combination (:749) | ungated | partial (AJAX HTML) | aizuploader per variant | none | port as a client-side variant grid; retire the endpoint |
| product/products/sku_combinations_edit | `products.sku_combination_edit` · POST (:118) | ProductController@sku_combination_edit (:781) | ungated | partial (AJAX HTML) | aizuploader per variant | none | port as above |
| product/reviews/index | admin GET …/reviews (:551) is unnamed; `reviews.index` went to the storefront GET /temp/reviews (shop-web:358) | ReviewController@index (:42) | `view_product_reviews` | list | `$.post reviews.published` | **LegacyPaginationTest:238 (renders)** | port; give the admin route its own name |
| uploaded_files/create | `uploaded-files.create` (:646) | AizUploadController@create (:57) | ungated | form (Uppy dashboard) | `AIZ.plugins.aizUppy` → `/aiz-uploader/upload` | none | port into the uploads replacement (§2.4) |
| uploaded_files/index | `uploaded-files.index` (:646) | AizUploadController@index (:50) | ungated | list (60-per-page grid, search/sort) | `$.post uploaded-files.info` → `.html(data)`; bulk delete; copy URL | SCR | port into §2.4 |
| uploaded_files/info | `uploaded-files.info` · ANY …/uploaded-files/file-info (:648) | AizUploadController@file_info (:217) | ungated + UploadPolicy | modal partial (AJAX HTML) | none | none | port-trim: a client-side panel from JSON; the GET is shadowed by the resource `show` route |

Notes:
1. HTML over AJAX: `products.sku_combination[_edit]`, `uploaded-files.info`, `/temp/aiz-uploader` (the modal); `products.add-more-choice-option` returns unescaped `<option>` strings.
2. Redirects that drop `/temp`: `admin_product_edit`/`seller_product_edit` send digital products to `redirect('admin/digitalproducts/…')`, which lands in the warehouse app.
3. `ProductController@get_products_by_subcategory` (route :109) does not exist; the `uploaded-files` resource's store/show/edit/update map to missing methods.
4. Two upload paths bypass aiz on purpose: the offers catalogue (`OffersCatalogStorage`, private disk) and bulk import (plain file input). Keep both out of §2.4.

### trips (19 views) and representatives (10)

URIs are under `/temp/admin`. Own = `LegacyAdminOwnershipTest` (its renders only assert "not 403"); SCR as above; "ungated" = listed in `LegacyAdminPermissionsTest`'s ratchet lists.

| View | Route name · verb URI (route line) | Controller@method (render line) | Permission | Kind | JS it depends on | Tests | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- |
| trips/index | `trips.index`, `trips.today-trips` (:245, :250) | Trip\TripController@getAllTrips/@getTodayTrips (:396) | `view_all_trips` | list | `$.ajax trips.bulk-trip-delete`, `trips.update-notes` (JSON), status modal, confirm-delete | Own:101 | port-trim: delete link gated by `delete_customer` (should be `delete_trip`); edit/status/notes buttons have no `edit_trip` check |
| trips/orders | `trips.orders` (:246) | TripController@all_orders (:721) | `view_all_trips` | list | aiz-date-range; `bulk-order-delete`, `…bulk-change-delivery-status`, `…update_invoice_number`, `…update_order_status_to_delivered` (JSON); google.com/maps links | none | port-trim: dead manager-notes modal, "Change status" actually marks delivered, nested `<form>`, broken `onchange` typo |
| trips/create | `trips.create` (:251) | TripController@create (:59) | `add_trip` | form | aiz-selectpicker; includes inc/scripts, inc/map-scripts, checkpoint partials | Own:102 | port (`checkpoints_array` is never validated by TripRequest) |
| trips/edit | `trips.edit` · GET …/trips/edit/{id} (:256) | TripController@edit (:161) | `edit_trip` | form | as create | none | port (`Trip::find` 500s on a missing id; existing checkpoints never plotted) |
| trips/create-from-map | `trips.create-from-map` (:252) | TripController@create_from_map (:71) | `add_trip` | form (map picker) | Leaflet 1.7.1 (unpkg) + OSM tiles; `fetch` → `trips.store-from-map`, `…update_order_status_to_delivered` | none | port-trim: drop inc/scripts (throws) and inc/styles; `$truck->lastTrip()->id` fatals when a busy truck has no open trip |
| trips/checkpoints/index | `trips.show` · GET …/trips/show/{id} (:255) | TripController@show (:52) | `view_all_trips` | show + print | Leaflet + OSM, OSRM route computed server-side, `$.ajax checkpoints.update-notes`, `window.print()`, CMR link | none | port-trim: print CSS never output (`@section('style')` has no yield); crashes on a trip with no checkpoints |
| trips/components/checkpoint-template | (via create/edit) | — | add/edit_trip | partial | hidden clone template; Eloquent queries inside the view | indirect | port as one Vue checkpoint-row component |
| trips/components/checkpoint | (via create/edit) | — | add/edit_trip | partial | bootstrap-select + inline script per row | none | port, merged with checkpoint-template |
| trips/components/update-trip-status-modal | (via index) | — | endpoint needs `edit_trip` | modal partial | `$.post trips.update-status` | indirect | port |
| trips/components/update-checkpoint-status-modal | (via checkpoints/index) | — | endpoint needs `edit_trip` | modal partial | `$.post checkpoints.update-status` | none | port |
| trips/inc/scripts | (via create/edit/create-from-map) | — | add/edit_trip | partial (JS) | SortableJS `@latest` (jsdelivr), `$.ajax trips.available-vehicles` (JSON) | indirect | port as a composable |
| trips/inc/map-scripts | (via create/edit) | — | add/edit_trip | partial (JS) | Leaflet + OSM, `fetch trips.user-coordinates` (JSON; geocodes through OpenCage) | indirect | port |
| trips/inc/styles | (included inside `@section('style')`) | — | — | partial (CSS) | none | — | **delete**: the layout has no `@yield('style')`, so it never reaches the page |
| trips/drivers/index | `drivers.index` (:273) | Trip\DriverController@index (:30) | `view_all_drivers` | list | confirm-delete | Own:108; SCR | port-trim: delete link gated by `delete_customer` (should be `delete_driver`) |
| trips/drivers/create | `drivers.create` (:274) | DriverController@create (:37) | `add_driver` | form | aiz-selectpicker | Own:109 | port-trim: dead recaptcha div |
| trips/drivers/edit | `drivers.edit` · GET …/edit/{id} (:276) | DriverController@edit (:68) | `edit_driver` | form | aiz-selectpicker | Own:127,144,179 (non-200 paths only) | port-trim: dead recaptcha div |
| trips/trucks/index | `trucks.index` (:282) | Trip\TruckController@index (:28) | `view_all_trucks` | list + print | qrcode 1.5.0 + jsPDF 2.4.0 (cdnjs): QR PDF built and printed client-side | Own:113 | port (no delete UI: `trucks.destroy`/`bulk-truck-delete` point at missing methods) |
| trips/trucks/create | `trucks.create` (:283) | TruckController@create (:33) | `add_truck` | form | aizuploader (multiple) | none | port |
| trips/trucks/edit | `trucks.edit` (:285) | TruckController@edit (:67) | `edit_truck` | form | aizuploader | none | port (`find()` 500s on a missing id) |
| representatives/index | `representatives.index` (:209) | CustomerController@repIndex (:441) | `view_all_customers` | list | `$.post rep.packages.modal` → `.html(data)`; bulk delete; ban/unban modals | SCR (ban buttons) | port-trim: orphan `<td>`s misalign columns |
| representatives/create | `representatives.create` (:210) | CustomerController@repCreate (:446) | `view_all_customers` | form | double-submit guard | none | port |
| representatives/packages/index | `rep.packages.index` (:232) | RepresentativePackageController@index (:15) | ungated | list (cards) | confirm-delete | SCR | port; needs a permission name (§7.1) |
| representatives/packages/create | `rep.packages.create` (:233) | RepresentativePackageController@create (:21) | ungated | form | none | none | port; needs a permission name |
| representatives/packages/edit | `rep.packages.edit` (:235) | RepresentativePackageController@edit (:47) | ungated | form | none | none | port; needs a permission name |
| representatives/packages/modal | `rep.packages.modal` · POST (:238) | RepresentativePackageController@package_modal (:110) | ungated | modal partial (AJAX HTML) | posts to `rep.set-package` | none | port as a Vue dialog fed by props; retire the endpoint |
| representatives/payments/index | `admin.rep.payments.index` · GET /temp/admin/**admin**/representatives/payments/index (:216) | RepresentativePaymentAdminController@index (:68) | ungated | list | accept/reject `data-method="post"`; export is a server PDF | SCR:27 | port-trim: dead ban/unban modals, package modal, checkbox column |
| representatives/visits/index | `admin.rep.visits.index` (:224) | VisitController@index (:38) | ungated | list | aiz-date-range, `$.ajax bulk-visit-delete` | SCR:29 | port |
| representatives/visits/show | `admin.rep.visits.show` (:225) | VisitController@show (:55) | ungated | show | Leaflet + OSM, one marker | none | port (null coordinates break the JS) |
| representatives/visits/rep-show | `admin.rep.visits.rep-show` (:226) | VisitController@show_all_visits_in_a_day (:78) | ungated | show (map) | Leaflet markers + polyline | none | port-trim: each marker drawn twice |

Notes:
1. Maps: no Google Maps JS here. Leaflet 1.7.1 from unpkg with OSM tiles (no key); routes from the public OSRM demo server over plain HTTP (TripController.php:514, :569); geocoding through OpenCage (`services.opencage.key`, UserService.php:80), and both GET create-from-map and `trips.user-coordinates` write coordinates back into addresses. A Vue map needs a Leaflet dependency (owner approval: CLAUDE.md "no new dependencies without approval").
2. Only `rep.packages.modal` returns HTML; everything else answers JSON, `1`/`0` or a redirect.
3. The trips, trucks, drivers and visits controllers are excluded from the "no `back()`" rule (`ControllerRedirectsTest.php:61-65`); converting them removes the exclusions.
4. The representatives packages/payments/visits screens and the order-entry screens have no permission name yet (§7.1): their batch must add the names first.

### sellers (13 views), customer (13), admin_profile (1)

URIs are under `/temp/admin` unless shown. No view here is rendered by any test; coverage is the 403 sweep, the SCR source scan, two "not 403" checks and the payout form-target tests.

| View | Route name · verb URI (route line) | Controller@method (render line) | Permission | Kind | JS it depends on | Tests | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- |
| admin_profile/index | `profile.index`, `profile.update` (:308, 2 routes) | ProfileController@index, @update | ungated by design: self-service, `update` is self-only (`AdminProfileRequest`) | form → PATCH `profile.update` | aizuploader (single id) | LegacyAdminProfileTest | port |
| customer/classified_products/index | `classified_products` (:611) | CustomerProductController@customer_product_index (:52) | `view_classified_products` | list | `$.post classified_products.published`, confirm-delete | SCR | port (behind `classified_product`); destroy `data-href` ends in `}}}}` |
| customer/customer_packages/create | `customer_packages.create` (:603) | CustomerPackageController@create (:52) | `edit_classified_package` | form | aizuploader | none | port |
| customer/customer_packages/edit | `customer_packages.edit` · GET …/edit/{id}?lang= (:605) | @edit (:101) | `edit_classified_package` | form (language tabs) | aizuploader | none | port |
| customer/customer_packages/index | `customer_packages.index` (:603) | @index (:42) | `view_classified_packages` | list (cards) | confirm-delete | SCR | port |
| customer/customers/address_modal | (included by customers/edit:266) | CustomerController@edit (:254) | `view_all_customers` | modal partial | 3× aiz-selectpicker; `$.ajax addresses.edit` → JSON `{html}` injected; `get-state`/`get-city` → `<option>` HTML injected; google_map partial | none | port (rework): admin edits of a customer's address get 403 from `AddressPolicy::manage`, so it needs admin-side address endpoints |
| customer/customers/archive | `customers.archive` · GET …/customers/archive/get (:190) | @archive (:161) | `delete_customer` | list | dead bulk-delete JS, notes modal, ban/unban modals, login-as, restore | LegacyAdminPermissionsTest:238 (not 403); SCR | port-trim: fold into index as an "archived" filter |
| customer/customers/create | `customers.create` (:185) | @create (:173) | `view_all_customers` | form → `customers.store` = POST …/customer/store → `store_customer` (:189) | none | RouteServiceProviderTest:49 | port; pick one store handler (StoreUserMassAssignmentTest covers the unnamed `@store`) |
| customer/customers/edit | `customers.edit` (:185) | @edit (:254) | `view_all_customers` | form → POST `customers.update` (:188) | address_modal; links to trade license, `addresses.destroy`, `customers.sendAgreement` | SCR | port |
| customer/customers/edit_password | `change_password` (:191) | @change_password (:601) | `view_all_customers` | form → `update_password` | none | GET_ROUTES_THAT_MAY_STAY | port, folded into edit |
| customer/customers/index | `customers.index` (:185) | @index (:105) | `view_all_customers` | list | bulk delete `$.ajax`, notes modal, confirm-delete, ban/unban modals, login-as, verify/important/activity POST links | SCR | port (Package column header and cell use different conditions) |
| customer/customers/views | `customers.page-views` (:199) | @pages_views (:744) | `view_all_customers` | list | none | none | port (category link goes to an empty `CategoryController@show`) |
| customer/notifications/create | `admin.notifications.create` (:663) | AdminNotificationController@createNotification (:53) | `add_notification` | form → `admin.notifications.send` (FCM HTTP v1) | aiz-selectpicker; inert select2 markup | none | port-trim: drop the select2 markup |
| customer/notifications/index | `admin.notifications.all` (:661) | @getAllNotifications (:35) | `add_notification` | list | confirm-delete | LegacyAdminPermissionsTest:261; SCR | port-trim: commented-out search and categories script |
| sellers/create | `sellers.create` (:155) | SellerController@create (:80) | `edit_seller` | form | none | none | port |
| sellers/edit | `sellers.edit` · GET …/sellers/{encrypted}/edit (:155) | @edit (:146) | `edit_seller` | form | none | none | port |
| sellers/index | `sellers.index` (:155) | @index (:70) | `view_all_seller` | list | aiz-selectpicker; bulk delete; `$.post sellers.payment_modal`/`profile_modal` → `.html(data)`; approve toggle; a `.select2()` call that throws; login-as | payout (redirect target); SCR | port-trim: drop `.select2()`; the approve toggle checks `approve_seller`, which exists nowhere, while the endpoint needs `edit_seller` |
| sellers/payment | `sellers.payment_history` · GET …/seller/payments/show/{encrypted} (:173) | PaymentController@show (:81) | `seller_payment_history` | list | none | none | port, folded into payment_histories as a filter |
| sellers/payment_histories/index | `sellers.payment_histories` (:172) | @payment_histories (:46) | `seller_payment_history` | list | none | none | port |
| sellers/payment_modal | `sellers.payment_modal` · POST (:165) | SellerController@payment_modal (:257) | `pay_to_seller` | modal partial (AJAX HTML) | inline selectpicker; posts to `commissions.pay_to_seller` | payout | port as a Vue dialog; retire the endpoint |
| sellers/profile_modal | `sellers.profile_modal` · POST (:166) | @profile_modal (:263) | `view_seller_profile` | modal partial (AJAX HTML) | none | none | port as a dialog fed by props |
| sellers/seller_commission/index | `business_settings.vendor_commission` (:341) | BusinessSettingsController@vendor_commission (:492) | `seller_commission_configuration` | form (settings) | toggles post to `business_settings.update.activation` (RaizerSuperAdmin only) | none | port; the toggles need an endpoint gated by the page's permission |
| sellers/seller_verification_form/index | `seller_verification_form.index` (:339) | @seller_verification_form (:362) | `seller_verification_form_configuration` | form (field builder) | plain jQuery DOM appends | none | port |
| sellers/seller_withdraw_requests/index | `withdraw_requests_all` (:179), also `withdraw_requests.index` (:177) | SellerWithdrawRequestController@index (:37) | `view_seller_payout_requests` | list | `$.post …payment_modal`/`message_modal` → `.html(data)`; a throwing `.select2()` | payout; LegacySellerWithdrawRequestTest:117 | port-trim: drop `.select2()`; "Pay Now" has no `@can` while "Message View" is gated on `pay_to_seller` |
| sellers/seller_withdraw_requests/payment_modal | `withdraw_request.payment_modal` · POST (:180) | @payment_modal (:99) | `view_seller_payout_requests` (submit needs `pay_to_seller`) | modal partial (AJAX HTML) | inline selectpicker | payout | port as a dialog |
| sellers/seller_withdraw_requests/withdraw_message_modal | `withdraw_request.message_modal` · POST (:181) | @message_modal (:109) | `view_seller_payout_requests` | modal partial (AJAX HTML) | none | none | port: show the message inline, retire the endpoint |
| sellers/verification | `sellers.show_verification_request` (:160) | SellerController@show_verification_request (:218) | `view_all_seller` | show | approve/reject POST links; verification file links | LegacyShopVerificationTest (download only); SCR | port |

Notes:
1. Four admin endpoints answer a POST with Blade HTML that the page injects (`sellers.payment_modal`, `sellers.profile_modal`, `withdraw_request.payment_modal`, `withdraw_request.message_modal`); the storefront `addresses.edit` returns `{html}` and `get-state`/`get-city` return `<option>` strings.
2. "Login as" (`sellers.login`, `customers.login`, GET with `encrypt(id)`) replaces the admin's session with the customer's on the store guard and redirects into the storefront: in Vue a plain `<a>` (full page load), never an Inertia visit.
3. `CustomerController` gates every write behind the read permission `view_all_customers` (update, update_password, store_customer, verify, important, notes); `customers.sendAgreement` and the `addresses.*` routes live in shop-web.php outside `temp/admin`, so the permission ratchet never sees them; `addresses.store` accepts a posted `customer_id` from any signed-in user.
4. select2 is not in vendors.js: the `.select2()` calls in sellers/index and the withdraw-requests list throw.
5. Confirm with the owner that multi-vendor (`vendor_system_activation`) and classifieds (`classified_product`) are in use before porting their 17 views.

### sales (9 views), reports (8), invoices (1)

URIs are under `/temp/admin` unless shown.

| View | Route name · verb URI (route line) | Controller@method (render line) | Permission | Kind | JS it depends on | Tests | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- |
| invoices/invoice | `invoice.download` · GET /temp/invoice/{order_id} (shop-web:355); `seller.invoice.download` (shop-seller:74) | InvoiceController@invoice_download (:80), Seller\InvoiceController (:69) | `Gate::authorize('downloadInvoice')` (OrderPolicy) | print (mPDF) | none (QR SVG) | LegacyStoreInvoiceTest:36; Policies/OrderPolicyTest:28 | keep Blade (PDF) |
| reports/commission_history_report | `commission-log.index` (:523) | ReportController@commission_history (:164) | `commission_history_report` | list | includes the partial | Own:78 (renders) | port-trim: fold the partial in, drop the dead seller branch |
| reports/partials/commission_history_section | (via the page above) | — | as above | partial | aiz-selectpicker, aiz-date-range | indirect | merge into the page |
| reports/in_house_sale_report | `in_house_sale_report.index` (:518) | ReportController@in_house_sale_report (:102) | `in_house_product_sale_report` | list | aiz-selectpicker | none | port |
| reports/seller_sale_report | `seller_sale_report.index` (:519) | ReportController@seller_sale_report (:115) | `seller_products_sale_report` | list | aiz-selectpicker | none | port-trim: `$seller->shop` is not an attribute (MissingAttributeException in strict mode); per-row sums are N+1 |
| reports/stock_report | `stock_report.index` (:520) | ReportController@stock_report (:90) | `products_stock_report` | list | aiz-selectpicker | none | port |
| reports/user_search_report | `user_search_report.index` (:522) | ReportController@user_search_report (:132) | `user_search_report` | list | none | none | port |
| reports/wallet_history_report | `wallet-history.index` (:524) | ReportController@wallet_transaction_history (:195) | `wallet_transaction_report` | list | aiz-selectpicker, aiz-date-range | Own:71 (validation only) | port-trim: dead seller check; "Approval" column is offline-payment addon data |
| reports/wish_report | `wish_report.index` (:521) | ReportController@wish_report (:127) | `product_wishlist_report` | list | aiz-selectpicker | none | port (use `withCount`) |
| sales/edit-shipping-order | `new.feat-order.trip.edit` · GET …/feat/new/order/trip/edit/{id} (:447) | FeatureOrderTripController@newEdit (:223), @newUpdate | ungated | form | select2 4.1 (CDN), `$.ajax products.get-details` (JSON), PUT JSON returning a URL | none | port as the shared order-entry component; newUpdate stores the line total as the unit price and drops variations |
| sales/index | `all_orders.index`, `inhouse_orders.index`, `seller_orders.index`, `pick_up_point.index` (:487-490) | OrderController@all_orders (:156) | `view_all_orders\|view_inhouse_orders\|view_seller_orders\|view_pickup_point_orders` + per-route check (:85-113) | list | aiz-selectpicker, aiz-date-range, bulk checkboxes, `$.ajax bulk-order-delete`, `$.post orders.update_manager_notes` | RouteServiceProviderTest:51; SCR:183 | port-trim: refund column, commented bulk-status JS; `date` filter unvalidated (500 on bad input) |
| sales/new-new-order | `feat-order.create` · GET …/feat/order/create (:433) | FeatureOrderController@create (:17), @store | ungated | form | select2 (CDN), `products.get-details`, POST JSON returning a URL | none | port as the shared order-entry component |
| sales/new-order | `feat-order.trip.create` (:440) | FeatureOrderTripController@create (:19) | ungated | form | `$.get feat-order.trip.needed-data` → `.html(data)` | none | **delete** with routes :435, :440-442, :450: nothing links to it; replaced by new-shipping-order |
| sales/new-shipping-order | `new.feat-order.trip.create` (:444) | FeatureOrderTripController@newCreate (:214), @newStore | ungated | form | select2 (CDN), `products.get-details`, POST JSON returning a URL | none | port as the shared order-entry component (drop dead `.rep-search` JS, which throws on load) |
| sales/products-data-repeater | none | none | — | partial | jQuery UI sortable | none | **delete**: nothing renders it |
| sales/products-data-table | none (route commented out at :436, :452) | `productsData` — missing | — | partial | inline script | none | **delete**: nothing renders it |
| sales/show | `orders.show` + 4 aliases (:483, :492-495) | OrderController@show (:184) | `view_order_details` | show | aiz-selectpicker; `$.post orders.update_payment_status`, `update_tracking_code`, `order.item.edit_price`, `delivery-boy-assign` (dead addon); invoice link | none | port-trim: delivery-boy, manual-payment and auction sections, the hard-coded `disabled` delivery-status select; the view's `@can` checks for payment/delivery status are not enforced server-side |
| sales/user-data-table | `feat-order.trip.needed-data`, `feat-order.needed-data`, `new.feat-order.trip.needed-data` (:442, :435, :450) | FeatureOrderTripController@getNeededData (:29), FeatureOrderController@getNeededData (:27), @newGetNeededData (:296) | ungated | partial (AJAX HTML) | footable; its inline script never runs after injection | none | **delete**: only new-order calls it |

Notes:
1. The three order-entry screens are ~95% the same view (rep select, target and pre-filled rows differ); port them as one Vue component with async customer/product search, and give them a permission name first (§7.1).
2. `Model::shouldBeStrict()` throws on lazy loads once a list has 2+ rows; no list view here has a render test. The Inertia controllers must eager-load what each table reads.
3. The invoice stays a server-rendered PDF (mPDF through the `PDF` alias; §1 plans dompdf + ar-php). Admin hardcodes `'sa'` fonts; the seller controller passes no `cartResults`.

### dashboard, website_settings, support, system (15 views)

| View | Route name · verb URI (route line) | Controller@method (render line) | Permission | Kind | JS it depends on | Tests | Verdict |
| --- | --- | --- | --- | --- | --- | --- | --- |
| dashboard | `shop.admin.dashboard` · GET /temp/admin (:71) | AdminController@admin_dashboard (:62) | ungated (READS list); view wraps all in `@can('admin_dashboard')`, SMTP alert `smtp_settings` | show | Chart.js 2.8 via `AIZ.plugins.chart` ×5, `aiz-carousel` (slick) | LegacyAdminPanelTest:34,42,78,164; LegacyTempRoutesReachableTest:24; StoreRoleMiddlewareTest:100; RouteServiceProviderTest:86 | port-trim: drop graph-3/`total_visits` card (never written since CountWebRouteVisits was dropped), move ~10 inline counts into the controller, send arrays not comma strings from `cached_graph_data` |
| website_settings/header | `website.header` · GET …/website/header (:394) | WebsiteController@header (:30) | `header_setup` | form (settings) | aizuploader ×4, add-more repeater, aiz-switch | none | port (keep the misspelt `header_stikcy` key: the storefront reads it) |
| website_settings/footer | `website.footer` · GET …/website/footer?lang= (:393) | WebsiteController@footer (:35) | `footer_setup` | form (settings, translatable) | language tabs, aizuploader ×2 (one multiple), summernote ×2, repeater, aiz-switch | none (storefront output: HtmlSanitizerTest:189) | port-trim: drop Delivery Boy app link (304-312, dead addon); Seller app link is an owner call |
| website_settings/appearance | `website.appearance` · GET …/website/appearance (:395) | WebsiteController@appearance (:43) | `website_appearance` | form (settings) | aizuploader ×9, summernote ×2, aiz-switch | none | port-trim: drop `delivery_boy_login_page_bg` (103-118); re-gate the custom-script card (see note 1) |
| website_settings/pages/index | `website.pages` · GET …/website/pages (:396) | WebsiteController@pages (:39) | `view_all_website_pages` (buttons add/edit/delete_website_page) | list | confirm-delete `data-method="post"` | LegacyStoreStateChangingRoutesTest:43 (destroy verb only) | port |
| website_settings/pages/create | `custom-pages.create` · GET …/website/custom-pages/create (:400) | PageController@create (:49) | `add_website_page` | form | summernote (full toolbar), aizuploader | none | port |
| website_settings/pages/edit | `custom-pages.edit` · GET …/custom-pages/edit/{slug}?lang= (:402) | PageController@edit (:112) | `edit_website_page` | form (translatable) | language tabs, summernote, aizuploader | LegacyUploadSecurityTest:93 | port |
| website_settings/pages/home_page_edit | `custom-pages.edit` + `?page=home` (:402) | PageController@edit (:108) | `edit_website_page` | form (settings) | repeater rows with aizuploader, repeater of `aiz-selectpicker` | none | port-trim: delete commented-out blocks and the Auction banner (424-451); stray `</form></div></div>` at 640-642 is live markup |
| support/support_tickets/index | `support_ticket.admin_index` · GET …/support_ticket (:557) | SupportTicketController@admin_index (:52) | `view_all_support_tickets` | list | GET search form | none | port (N+1 on user/replies) |
| support/support_tickets/show | `support_ticket.admin_show` · GET …/support_ticket/{enc}/show (:558); reply POST (:559) | SupportTicketController@admin_show (:190), @admin_store | `view_all_support_tickets` (form shows on `reply_to_support_tickets`) | show + reply form | summernote, aizuploader multiple | HtmlSanitizerTest:100 | port; keep server-side `clean_html()` |
| support/conversations/index | `conversations.admin_index` · GET …/conversations (:571) | ConversationController@admin_index (:59) | `view_all_product_conversations` | list | confirm-delete posting to storefront `conversations.destroy` | LegacyStoreStateChangingRoutesTest:41 | port (needs an admin-scoped delete route) |
| support/conversations/show | `conversations.admin_show` · GET …/conversations/{enc}/show (:572) | ConversationController@admin_show (:182) | `view_all_product_conversations` (reply checked in view only) | show + reply form | none | none | port (reply posts to storefront `messages.store`: no permission, no ownership check) |
| support/product_query/index | `product_query.index` · GET …/product-queries (:577) | ProductQueryController@index (:39) | `view_all_product_queries` | list | none | none | port-trim: unused delete modal; `translate($key + 1)` writes a translation row per number |
| support/product_query/show | `product_query.show` · GET …/product-queries/{enc} (:578); reply PUT (:579) | ProductQueryController@show (:48), @reply | `view_all_product_queries` | show + reply form | none | none | port (`find()` without null check 500s) |
| system/server_status | `system_server` · GET …/system/server-status (:643) | `Route::view` | `RaizerSuperAdmin` | show | none | none | delete: superseded by the WMS `/health` page (PHP "7.3/7.4" check always red, MySQL-only `select version()`, installer-era writable checks) — owner call |

Notes:
1. Every settings form posts `types[]` + values to `business_settings.update` (BusinessSettingsController@update, shop-admin.php:312), which upserts any key with no allowlist, runs `cache:clear` and returns `back()`. Its gate is an OR of eight permissions (:44), so a user with only `currency_setup` can write `header_script`/`footer_script`, which the storefront prints raw. Port as one endpoint per settings page, with that page's permission and an allowlist of keys.
2. No view here makes its own AJAX call; every `data-toggle="aizuploader"` posts to `/temp/aiz-uploader` (AizUploadController@show_uploader), which returns the modal's HTML.
3. Write gates disagree with the buttons: ticket reply checks `reply_to_support_tickets` in the view only; product-query reply checks `reply_to_product_queries` in the view only; conversation reply likewise. The port gates the endpoint, not the button.
4. The sidenav "Home Banners" link hardcodes `config('app.url').'/admin/website/custom-pages/edit/home?page=home'`, which misses `/temp` and lands on the WMS `/admin`.
5. `PageController@index` and `@show` are empty: GET …/website/custom-pages and …/custom-pages/{id} answer a blank 200.

### layouts (3 views) and inc (2 views)

| View | Used by | Kind | What it carries | Verdict |
| --- | --- | --- | --- | --- |
| layouts/app | 138 back-office views remain (161 before the staff pilot; 155 before batch 1) | layout | vendors.css/js + aiz-core.css/js, `bootstrap-rtl.min.css` and `dir="rtl"` from `languages.rtl` (:2-6, :25), 2 inline scripts (`AIZ.local` uploader strings :37-59, flash notify loop + language switcher + `menuSearch()` :88-134), Poppins from Google Fonts. Yields `content`, `modal`, `script` only — **no `style` yield**, so the `@section('style')` in 7 views (3 order-entry, 4 trips) is dropped | replaced by `StoreAdminLayout.vue`; delete with the last Blade module |
| layouts/blank | nothing (0 users) | layout | leftover installer CSS | **deleted 2026-10-10** after a repository-wide consumer trace found no references |
| layouts/layout | `shop/frontend/seller_login`, `deliveryboy_login` (HomeController.php:71,73), `shop/errors/503` | layout (no nav) | same assets as app | stays: it serves the storefront's seller login, not the back office |
| inc/admin_sidenav | layouts/app:66 | partial (sidebar, 1747 lines) | the menu tree (§2.2); 12 removed-addon blocks link to route names that no longer exist; malformed `@canany`/`@endif` nesting in Setup & Configurations (L1392-1650); a stray top-level duplicate "Shipping" group (L1654-1687); "Home Banners" hardcodes a URL without `/temp` (L771-777) | replaced by `lib/storeAdminMenu.ts`; delete with the last Blade module |
| inc/admin_nav | layouts/app:68 | partial (top bar) | browse-website link (`home`), Clear Cache (`cache.clear`, raizer admin), notifications dropdown (unread, `admin.all-notification`), language switcher (POST `language.change`, empty 200, then reload), user menu (`profile.index`, `shop.logout`) | replaced by the layout's header/account block |

Notes:
1. The aiz uploader posts to `AIZ.data.appUrl + "/aiz-uploader"`, where `appUrl` is `getBaseURL()` (no `/temp`), while the uploader routes exist only under `/temp`. Check in a browser before relying on the Blade uploader during coexistence.
2. `admin.all-notification` returns a view only for `user_type` admin/seller/customer: staff and accountants (both let through by `IsAdmin`) get an empty 200.
3. `Language` middleware writes `session('locale')` on every request and runs after WMS's `SetLocale` (en/ar only), so it wins on `/temp` pages; both use the same session key.
