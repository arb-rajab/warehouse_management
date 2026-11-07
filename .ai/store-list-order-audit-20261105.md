# Store back office default list order audit, 2026-11-05

Historical evidence (not current guidance); the standing rule is
[store-list-default-order](rules/store-list-default-order.md). Scope: every list
action under `app/Http/Controllers/Store/Admin/**` that feeds an Index page, read on
branch `ccr-9db071d0-5jca06`. The warehouse admin is out of scope. Nothing was run;
this is a source reading plus the changes listed. No `Index.vue` sorts client side
(`DataTable`'s `sort` prop is unused on the store pages), so no page changed, and no
`Filter*Request` gained or lost a sort parameter. No model global scope orders a
store list (`Role`/`Permission` scopes only filter by guard).

Target: `created_at` desc, then id desc (`App\Support\NewestFirst`). Every store list
table has `created_at` (migrations under `database/migrations/*create_*`), so the
timestamp column below is `created_at` throughout.

## Changed to newest first

"Before" is the order the controller applied.

| List (controller) | Before | User sort | After |
|---|---|---|---|
| Roles | id asc | none | newest first |
| Languages | id asc | none | newest first |
| Customer packages | id asc | none | newest first |
| Cities | status desc, id asc | none | newest first |
| Countries | status desc, id asc | none | newest first |
| States | status desc, id asc | none | newest first |
| Brands | name, id | none | newest first |
| Blog categories | category_name, id | none | newest first |
| Brand discounts | id asc | none | newest first |
| Dynamic popups (joins `uploads`) | `dynamic_popups.id` asc | none | newest first, columns qualified |
| Website pages | id asc | none | newest first |
| Trucks | id asc | none | newest first |
| Truck photo picker | id desc | none | newest first |
| Drivers | id asc | none | newest first |
| Customers and representatives (joins a profit sub-query) | `users.id` asc | none | newest first, `users.` qualified |
| Staff | id asc | none | newest first |
| Customer notifications | id asc | none | newest first |
| Representative packages | id asc | none | newest first |
| Representative payments | created_at desc, id asc (wrong tie-break) | none | id desc tie-break |
| Orders | id desc | none | created_at desc, id desc |
| Admin notifications (UUID ids, so id order was arbitrary) | id desc | none | created_at desc, id desc |
| Taxes, zones, currencies, colors, carriers, attributes, attribute values, pickup points, coupons, offers, flash deals, offers catalogs | id desc | none | created_at desc, id desc |
| All products | id asc | legacy `type` select | newest first; `type` still wins |
| Stock and wishlist reports | created_at desc, id desc | none | same, now via the helper |

## Already newest first, moved onto the helper

Reviews (the `rating` select still wins, newest first breaks its ties), subscribers,
in-house and seller products (the `type` select wins), digital products, classified
products, trips, trip orders, visits, support tickets, product queries, conversations,
sellers, seller payments, seller withdrawals, seller sales report, commission and
wallet history reports, blog posts.

## Exceptions (order kept)

| List | Order | Reason |
|---|---|---|
| Categories | `order_level` desc, id | display priority staff maintain; the category tree reads by it |
| Order-entry search pickers | name, id | typeahead pickers, not tables |
| In-house sales report | `num_of_sale` desc, then newest | ranking by sales |
| Searches report | `count` desc, id desc | ranking by search count |
| Customer page views | view count desc | ranking by views |
| Translation keys (languages, app translations) | id | dictionary imported in one go; newest first is meaningless |
| Conversation messages | chronological | chat thread |
| Support ticket replies | id desc | reply thread, already newest first |
| File picker (`UploadController::files`) | `sort` parameter, default `newest` | has its own sort control already newest by default |

Not tables: select/option `->get()` lists, trip checkpoint sequences
(`sort_order_in_trip`), the dashboard top-products card.

## Judgement calls to confirm

- Cities, countries and states no longer list active rows before inactive ones, and
  brands and blog categories are no longer alphabetical. Both were treated as plain
  ordering habits, not meaning; restore them as listed exceptions if staff rely on them.
- Languages was offered as a possible exception; it orders by id, has no `sort_order`
  column, so it follows the rule.
- Store e2e specs (`tests/e2e/store-*.spec.ts`) were not read for first-row assumptions.
