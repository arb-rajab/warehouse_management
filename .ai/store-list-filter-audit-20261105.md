# Store back office list filter audit, 2026-11-05

Historical evidence (not current guidance); the standing rule is
[store-list-filter-placement](rules/store-list-filter-placement.md). Scope: every
`resources/js/pages/Store/Admin/**/Index.vue` (61 files), read on branch
`ccr-9db071d0-5jca06` at the state after PR #548's earlier commits. The warehouse
admin (`pages/Admin`) is out of scope. Nothing was run; this is a source reading.

## Finding

One real duplicate: `Product/Products/Index.vue`. The `#product-presets` links
(All products / Active / For sale / Featured / Deleted) sat above the table on
every product list, and the Filters dialog repeated Active / For sale / Featured
(and Deleted on the all list) as State checkboxes. The query keys differ
(`allActive` vs `active`), but `ProductController::all()` maps both to the same
columns. No other list rendered one filter twice.

The two controls were not equivalent on the all list: a preset applies one flag
on its own, the State boxes match a combination exactly. The preset links also
always target the all list, so on the in-house and seller lists they were
navigation, not a filter of that list.

## Table (before the change)

| List (`Store/Admin/...`) | Inline / top | In Filters dialog | Overlap |
|---|---|---|---|
| `Product/Products` (in-house, all, seller) | search; seller (seller list) or category (in-house, all); preset links (all three lists) | seller list: approved, category; all lists: sort `type`, state checkboxes (active, for_sale, featured, plus deleted on the all list) | Yes: preset links vs state checkboxes |
| `Customers/Customers` (customers, representatives) | search; `archived` | important, verified, from_api, registration_completed, activity_status, profit_from, profit_to | No (the profit-range banner only echoes `profit_*`) |
| `Sales/Orders` | search; `delivery_status` | `payment_status`, `from`, `to` | No |
| `Trips/Orders` | search; `delivery_status` | `from`, `to` | No |
| `Representatives/Payments` | search | status checkboxes (pending, accepted, rejected) | No |
| `Representatives/Visits` | search | `date_from`, `date_to` | No |
| `Trips/Trips` | search; `status[]` (hand-built form, no dialog) | none | No (two filters, no dialog needed) |
| `Sellers/Sellers` | search; approval select | none | No |
| `Product/Reviews` | rating/sort select | none | No |
| `Setup/Cities`, `Setup/States` | search; state or country select | none | No |
| `Setup/Countries` | search | none | No |
| Search-only: `Product/DigitalProducts`, `Brands`, `Categories`, `Colors`, `BrandDiscounts`, `Blog/Posts`, `Blog/Categories`, `Marketing/FlashDeals`, `Marketing/Offers`, `Marketing/DynamicPopups`, `Setup/Carriers`, `Currencies`, `PickupPoints`, `Support/Tickets`, `Trips/Drivers`, `Trips/Trucks` | search | none | No |
| `Customers/ClassifiedProducts` | none (per-page only) | none | No |
| Lists without filters, and form-style `Index.vue` pages (settings, profile, bulk upload, and similar) | none | none | n/a |

Category placement differs by product list type (inline on in-house and all, in
the dialog on seller where the inline slot holds the seller). They never render
together, so it is not a duplicate.

## Change made

- All list: the preset links stay at the top (the primary status switch); the
  State checkboxes are removed from the dialog and from `activeCount`.
- In-house and seller lists: the preset links are removed; the State checkboxes
  stay in the dialog.
- Per-page changes still carry any legacy state flag present in the URL.
- The now unused `store.products.allStateHint` and `store.products.deleted`
  locale keys were removed.
- Active-filter chips were added afterwards: `StoreListFilters` takes a `chips`
  prop and renders one removable chip per applied dialog filter plus a "Clear
  filters" link; the six pages that use it (Products, Customers, Orders,
  Trips/Orders, Representative Payments and Visits) pass one `appliedFilters`
  list, and the badge counts it. `Trips/Trips` and `Sellers/Sellers` have no
  dialog and no chips. The Customers profit-range banner stays beside its chip.
