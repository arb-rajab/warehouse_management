# Full feature checklist (admin panels + storefront)

A runnable checklist for an agent (or a person) to smoke-test every feature area.
Derived from `routes/web.php`, `routes/api.php`, `routes/shop-*.php` and
`resources/js/pages/**`. Re-derive it when routes change (`php artisan route:list`).

## 0. How to run it

**Setup (check what is already provisioned first):**

```bash
composer run setup            # only if vendor/, .env, APP_KEY or node_modules are missing
php artisan migrate:fresh --seed   # LOCAL/e2e DB ONLY. Never run against a shared DB.
composer run dev              # or: php artisan serve + npm run dev
```

Seeded accounts (`DatabaseSeeder`, refuses production):

| Area | Login URL | Account |
| --- | --- | --- |
| Warehouse admin | `/admin/login` | `test@example.com` / `password` |
| Store back office | `/temp/login` then `/temp/admin` | `store-admin@example.com` / `password` |
| Storefront customer | `/temp/login`, `/temp/register` | register a new one (section C) |

**Prerequisites learned from running this checklist** (a fresh `migrate:fresh --seed` is not enough):

- The default seed has warehouse data only. Add store data with
  `APP_ENV=local php artisan db:seed --class=StoreFilterCoverageSeeder --force`
  (accounts such as `store-filter-customer@example.invalid`, `store-filter-seller@example.invalid`, all `password`).
- Every storefront price needs a default currency: set the `system_default_currency` business setting to a currency id, or product pages return 404.
- Browsers must use HTTPS: the CSP upgrades asset requests, so `php artisan serve` over plain HTTP loads no assets.
  Use `node tests/e2e/support/serve-https.mjs` with `APP_URL=https://127.0.0.1:8010` and `TRUSTED_PROXIES=127.0.0.1`
  (see `playwright.config.ts` for the full environment, including a dedicated sqlite file per connection).
- Local and CI run Eloquent strict mode (`local`/`testing`): a lazy load of a relation on a list of two or more models throws there, though production does not.

Notes:

- The storefront, seller area and store back office all live under the `/temp` prefix.
  `/` redirects to an external site by design.
- The warehouse and store use **separate auth guards**; sign in to each separately.
- Warehouse API: `/api/v1/*` (Sanctum bearer). Store API: `/api/v1/store/*`.
  API docs: `/docs/api`.

**Automated coverage that already exists** (run these first, then do the manual items):

```bash
php artisan test --compact
npx vitest run
npm run types:check && composer run types:check
npm run test:e2e              # Playwright; run `python3 .claude/scripts/install_playwright.py` first
python3 .claude/scripts/pre_push_check.py --quick
```

**Pass criteria for every page item:** HTTP 200 (or the documented redirect), no
server error, no browser console error, no failed network request, no horizontal
scroll at 375px width, readable in dark mode, correct in `en` and `ar` (RTL).
Mark a check `[x]` pass, `[!]` fail (add a note), `[-]` not applicable/skipped (say why).

**Cross-cutting checks (apply to every list/form below, do not repeat per page):**

- [ ] **X1 Lists:** pagination works, per-page selector works, filters apply and show chips, filter reset works, default order is newest first.
- [ ] **X2 Columns (store lists):** the column picker shows/hides optional columns and the choice persists on reload.
- [ ] **X3 Forms:** required fields carry the required mark; submitting empty shows client-side errors first; a server validation error keeps the user's input (form retention).
- [ ] **X4 Success flash:** every create/update/delete/action that redirects shows a success message (never on failure).
- [ ] **X5 Destructive actions:** delete/logout/status changes ask for confirmation first.
- [ ] **X6 Detail views:** label above value, empty value shows `-`.
- [ ] **X7 Search selects (store):** customer/product/brand/city selects search the server, highlight the match and show the entity's row content.
- [ ] **X8 Status switches (store):** toggles update immediately, roll back and show an error on failure.
- [ ] **X9 Menu:** the sidebar highlights the active page, including on detail/edit pages.
- [ ] **X10 Authorization:** a user without the permission gets 403/redirect, not data (spot-check one route per area).
- [ ] **X11 i18n:** switch `en`/`ar`; no raw translation keys, layout mirrors in RTL.

---

## A. Warehouse admin panel (`/admin`, role `admin`)

### A1. Authentication and account
- [ ] `/admin/login` renders; wrong password shows a uniform error; throttling kicks in after repeated failures.
- [ ] Valid login lands on `/admin`; a user flagged for password change is routed to `/password/change`.
- [ ] `/password/change`: wrong current password rejected, weak new password rejected, success redirects with flash.
- [ ] Logout asks for confirmation and ends the session; `/admin` then redirects to login.
- [ ] A signed-in user without the `admin` role cannot open `/admin/*` (403/redirect).
- [ ] Locale switch (`POST /locale/{locale}`) persists across pages.

### A2. Dashboard (`/admin`)
- [ ] Stats cards and charts render with seeded data; dashboard filters (warehouse, product) change the numbers.
- [ ] Expiring-soon and stale-cell widgets link to the right filtered lists.

### A3. Warehouses (`/admin/warehouses`)
- [ ] List renders; create with valid data succeeds; invalid data shows errors (client and server agree).
- [ ] Edit saves; delete asks for confirmation and is blocked/handled when the warehouse has rows.
- [ ] `/admin/warehouses/search` returns options used by the warehouse filter select.

### A4. Rows and cells (`/admin/rows`)
- [ ] Create a row: letter is normalised (uppercase), full cell grid is auto-generated.
- [ ] Row show page: grid of cells, state colours/legend, highlight filters, deep links.
- [ ] Edit/resize a row: growing adds cells, shrinking is refused or safe when cells hold pallets.
- [ ] Delete a row (confirmation); export row QR codes (`/rows/{row}/export-qr-codes`) downloads a file.
- [ ] Cell actions: export a cell QR code; toggle active/inactive and see the state update.

### A5. Warehouse map (`/admin/warehouse-map`)
- [ ] Map loads rows via `/admin/warehouse-map/rows` (ETag/304 on reload); zoom/pan, hover and click-through to a row work.

### A6. Pallets (`/admin/pallets`)
- [ ] Matching-pallets list filters (product, warehouse, expiry) work.
- [ ] From a cell: store a pallet, then **open**, **remove boxes**, **empty**, **transfer** — each updates cell state and writes a cell log.
- [ ] Invalid actions (empty a full-state mismatch, transfer to an occupied cell) show an error banner, not a crash.

### A7. Receipts (`/admin/receipts`)
- [ ] List and filters by status; receipt show page lists pallets and attached files.
- [ ] Receipt file opens/downloads (`receipts/{receipt}/files/{file}`); a file from another receipt is 404.
- [ ] Finalized receipts are read-only.

### A8. Cell logs (`/admin/cell-logs`)
- [ ] Filters: product, pallet, row, column, user, action, date range; chips and reset work.
- [ ] State arrows/transfer arrows render (and mirror in RTL); table links go to the right objects.
- [ ] Flagged logs: acknowledge flags removes the flag.

### A9. Cell verification (`/admin/cell-verification-rounds`)
- [ ] Rounds list and round detail; correctness badges render.
- [ ] Accept a report; complete a round (confirmation); export a round's reports downloads a file.

### A10. Products (`/admin/products`)
- [ ] List, search and filters (`/products/search` select).
- [ ] Update box count and minimum pallets; invalid values rejected.
- [ ] Barcodes: add a barcode, duplicate on same product rejected (422), barcode owned by another product rejected (409/conflict message); preview, download one, download all; delete a barcode.
- [ ] Default barcode label/QR export and preview render.
- [ ] Reference image: upload (compressed), image-review show and submit.

### A11. Users (`/admin/users`)
- [ ] List, create, edit, delete (confirmation); role assignment (admin vs mobile user) is respected.
- [ ] User show page: activity history and verification reports render.
- [ ] Cannot delete yourself or the last admin (if enforced).

### A12. Settings and help
- [ ] `/admin/settings` loads, saves and flashes success.
- [ ] `/admin/help` index and every `/admin/help/{topic}` page render (including Receipts help).

### A13. Operations endpoints
- [ ] `/health` is 403 unless the user can `viewHealth` and the IP is allow-listed.
- [ ] `/robots.txt` responds.
- [ ] Telescope/Pulse (if enabled locally) are gated.

### A14. Mobile/warehouse API (`/api/v1`)
- [ ] `POST login` returns a token; bad credentials rejected; throttled when abused. `POST logout` revokes it; `GET user`.
- [ ] `GET dashboard`, `warehouses`, `rows`, `rows/full`, `rows/frozen` (ETag support: a repeat with `If-None-Match` is 304), `rows/{letter}/cells`, `rows/{letter}/cells/{cell}/flats/{flat}` (rows bind by **letter**, not id).
- [ ] `GET products`, `products/{id}`, `products/{id}/pallets`; `POST products/{id}/barcodes` (201 / 409 `barcode_taken` / 422).
- [ ] Pallets: `POST pallets` (needs `product_id`, `warehouse_id`, `row_letter`, `cell_number`, `flat_number`, `image_review_status`, and `image_source` equal to the product's current `image_url`), `GET pallets/unplaced`, `GET pallets/{id}`, `place`, `open` (only on levels where opening is allowed), `remove-boxes` (`boxes_count`), `empty`, `transfer` (`to_*` coordinates).
- [ ] A slot in a row that is in an active verification round answers 409 `rows_already_in_active_round`; storing on an occupied slot answers 409 `slot_not_empty`.
- [ ] `GET cell-logs`; verification rounds index/store/show/complete; `POST cell-verification-reports`.
- [ ] Receipts (admin role only): index, store, show, update pallets, finalize, file upload/show/delete.
- [ ] Unauthenticated calls return 401; minimum-app-version middleware rejects too-old clients.

---

## B. Store back office (`/temp/admin`, store admin guard)

Sign in at `/temp/login` as `store-admin@example.com`. Each item: list loads, create/edit/delete (where offered) work, X1–X11 hold.

### B1. Dashboard and profile
- [ ] `/temp/admin` dashboard: stat tiles and charts render; tiles link through to filtered lists.
- [ ] Profile page saves; avatar upload works.
- [ ] Notifications: `/notifications` list; send to customers (`/notifications/create`, `/notifications/customers` picker); `/all-notification` mark-as-read.

### B2. Catalogue
- [ ] **Categories:** list, create, edit, delete, featured toggle.
- [ ] **Brands:** list, create, edit, delete; **brand-wide product discounts** apply and clear.
- [ ] **Attributes / attribute values / colors:** CRUD for each.
- [ ] **Products** (in-house `/products/admin`, all `/products/all`, seller `/products/seller`): list + filters; create; edit; delete; bulk flags (featured, todays-deal, published) toggle.
  - [ ] Product form: variants, taxes, discount date range, images, barcodes (`product_barcodes_seen` posted), field-source ownership banners.
  - [ ] Barcode image route scopes to its product (another product's barcode is 404).
- [ ] **Digital products:** list, create, edit, download.
- [ ] **Bulk:** product bulk export; bulk upload page and the category/brand/seller template downloads.
- [ ] **Offers** (`/offers/index`, download), **offer deals**, **flash deals**: CRUD and product pickers; **campaign products/brands** lists.
- [ ] **Product data sources** (`/product-data-sources`): ownership of each synced field saves.
- [ ] **Reviews** (`/reviews`): list, publish/unpublish; the legacy URL redirects.
- [ ] **Product queries** and **conversations**: list, open, reply.

### B3. Sales and orders
- [ ] Order lists: all, in-house, seller, by pickup point; order detail pages; status/payment/delivery updates; invoice PDF download.
- [ ] Create an order (`/feat/order/create`) with customer + product search selects; edit an order; create/edit order with trip (`/feat/new/order/trip/*`).
- [ ] Order delete with confirmation.

### B4. Customers, representatives, sellers
- [ ] **Customers:** list, archive list, create (state/city cascade), edit, change password, WhatsApp contact, page-views, login-as, trade-licence download.
- [ ] **Customer packages** and **classified products**: CRUD / list.
- [ ] **Representatives:** list/create/edit; **payments** list/export; **visits** list, visit detail, per-rep visits; **rep packages** CRUD.
- [ ] **Sellers:** list, create, edit, verification form view + file download, login-as, approve/ban toggles.
- [ ] **Seller payments**, **payment show**, **withdraw requests** (list/all/create/show/edit), **commission** settings and logs.

### B5. Trips and logistics
- [ ] Trips: index, today's trips, create, edit, orders list, trip detail with checkpoint notes, status dialog, CMR print, map picker (`create-from-map`), available vehicles, user coordinates.
- [ ] Drivers CRUD; trucks CRUD, photos, truck QR.
- [ ] **Carriers, zones, countries, states, cities, pickup points:** CRUD. **Shipping configuration** and **order configuration** (incl. representatives) save.

### B6. Marketing and support
- [ ] Coupons CRUD (+ product options); newsletter send and recipient picker; subscribers list; dynamic popups CRUD.
- [ ] Blog categories and posts CRUD with the rich-text editor and image upload.
- [ ] Support tickets: list, show, reply; email deep links resolve the encrypted id.

### B7. Reports
- [ ] In-house sales, seller sales, stock, wishlist, user searches, commission log, wallet history: filters (X1) and export/print where offered.

### B8. Setup and settings
- [ ] General settings, activation, vendor commission, SMTP, social login, Facebook chat/comment, Google analytics/reCAPTCHA/Firebase/Map, payment methods, file system, verification form: each saves only its own keys and flashes success.
- [ ] **Currencies** (dialog create/edit, direct URL also works), **taxes** (inline add/edit), **languages** (create/edit/translations show/export).
- [ ] **Website:** header, footer, appearance, home page settings, custom pages (create/edit by slug), pages list.
- [ ] **Staff roles and staff:** role create/edit with permissions; staff create/edit; a staff member with limited permissions cannot reach pages outside their grants (X10).
- [ ] **Uploads / file manager:** upload (multipart), list, previews, delete.

---

## C. Storefront (`/temp`)

### C1. Public browsing (logged out)
- [ ] Home `/temp`: sliders, banners, featured categories, today's deal, flash deals, best sellers render; no broken images.
- [ ] Listings: `/category/{slug}`, `/brand/{slug}`, `/categories`, `/brands`, `/sellers`, `/coupons`, `/inhouse`, `/flash-deals`, `/flash-deal/{slug}`, `/todays-deal`.
- [ ] Product page `/product/{slug}`: gallery, variants change price, quantity, stock state, reviews, related products, add to cart, wishlist, compare, product query.
- [ ] Seller shop `/shop/{slug}` and `/shop/{slug}/{type}`.
- [ ] Search `/search?keyword=` (results, suggestions, filters, sorting, pagination, empty state).
- [ ] Classified: `/customer-products` (category/city/q filters), `/customer-product/{slug}`.
- [ ] Blog `/blog`, `/blog/{slug}`; custom pages `/{slug}`; policies (seller, return, support, terms, privacy); `/track-your-order`.
- [ ] Customer packages `/customer-packages`.
- [ ] SEO: `/temp/sitemap.xml` and `sitemap-{source}-{page}.xml`; `/robots.txt`; page has meta/Open Graph and JSON-LD; backend pages send noindex.
- [ ] Language toggle (en/ar) switches strings and direction and persists; currency switch updates prices.
- [ ] Newsletter subscribe (valid, duplicate, invalid email).

### C2. Accounts
- [ ] Register `/temp/register`: validation, duplicate email/phone, success; email verification link; terms activation link.
- [ ] Login `/temp/login` (and `/users/login`, `/seller/login`, `/deliveryboy/login` variants); wrong password, throttling, honeypot.
- [ ] Social login redirect/callback (only when enabled).
- [ ] Password reset: request, email link, set new password; resend code.
- [ ] Logout, and guest-only pages redirect a signed-in user to home.

### C3. Cart and checkout
- [ ] Cart: add, change quantity, remove, minimum order rules, stock limits, totals and tax; cart count in header.
- [ ] Coupon apply and remove (valid, expired, minimum not met).
- [ ] Checkout: choose customer (representatives), address select/create, delivery/shipping type, carrier, pickup point, payment select, place order.
- [ ] Payments: cash on delivery and wallet end to end; other gateways return 404/disabled when off (bkash, nagad, payhere, iyzico, sslcommerz, vogue-pay, n-genius, aamarpay: verify disabled behaviour, do not use live keys).
- [ ] `/order-confirmed` shows the order; order appears in the back office.
- [ ] Compare page add/remove; wishlist add/remove.

### C4. Customer account area
- [ ] Dashboard and profile edit (incl. trade licence, password change, email change callback).
- [ ] Purchase history list and detail; invoice download; digital purchases and download.
- [ ] Wishlist, followed sellers, wallet (balance, recharge request), club points.
- [ ] Addresses CRUD, set default (state/city cascade).
- [ ] Support tickets: create, list, reply. Conversations and product queries: create, reply.
- [ ] Product review submit.
- [ ] Notifications `/all-notifications`.
- [ ] Classified: create/edit/delete own listing, publish toggle.
- [ ] Representative-only: create customer, payments (create, show, export, QR, WhatsApp share), user search; a normal customer gets 403.

### C5. Seller area (`/temp/seller/*`, seller account)
- [ ] Dashboard; products list/create/edit/delete (+ digital products, bulk upload, publish/featured toggles, stock).
- [ ] Orders list/detail/status; invoices; reviews; coupons CRUD.
- [ ] Shop settings and verification application; profile; addresses.
- [ ] Payments, money-withdraw requests, commission history.
- [ ] Conversations, product queries, support tickets, notifications.
- [ ] A seller cannot read another seller's product/order (X10).

### C6. Store API (`/api/v1/store/*`)
- [ ] Auth: login (`login_by` email/phone), signup, social-login, password forget/confirm/resend, confirm code, `user`.
- [ ] Catalogue (public): products (home, featured, best-seller, todays-deal, new, digital, by category/brand/seller, search, search-barcode, variants, variant price), categories/brands/sliders/banners, shops, flash deals, classified, offers download, policies, languages, countries/states/cities.
- [ ] Customer (token): profile (counters, update, image), cart (summary, add, update, change-quantity, process, coupon), addresses, wishlists, reviews, purchase history (+ items/details), wallet (balance/history/pay), club points, refunds, chat, `order/store`, payments (wallet/cod/manual).
- [ ] Representative: get/pick/create customer, current customer, payments, visit notes.
- [ ] Seller API (`shop-api_seller`): own products, change status, orders.
- [ ] Delivery driver API: dashboard, deliveries by state, `change-delivery-status`, earnings/collection summaries.
- [ ] Trip API (`shop-api_trip`) and warehouse integration API (`/api/v1/store/warehouse/*`, `private-token`; invalid token = JSON `status: 401` with HTTP 200).
- [ ] Disabled gateways return 404; throttled routes return 429; unauthenticated returns 401; unknown `/api/v1/store/*` returns the fallback response.

---

## D. Report template

Return results as a table: `ID | Area | Result (pass/fail/skip) | Evidence (URL, status, screenshot path) | Note`.
List every failure first, with the exact URL, steps, expected vs actual, and any console or `storage/logs/laravel.log` error.
Do not fix anything during the run unless asked; do not edit data in a shared database; never print secrets.
