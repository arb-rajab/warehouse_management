---
paths:
  - 'resources/js/components/StoreSearchSelect.vue'
  - 'resources/js/components/StoreSearchSelect.test.ts'
  - 'resources/js/components/StoreSearchOptionRow.vue'
  - 'resources/js/components/StoreSearchOptionRow.test.ts'
  - 'resources/js/components/StoreOrderEntryFields.vue'
  - 'resources/js/components/StoreProductListField.vue'
  - 'resources/js/components/StoreSelectField.vue'
  - 'resources/js/lib/useSearchOptions.ts'
  - 'resources/js/lib/highlightMatch.ts'
  - 'resources/js/types/searchSelect.ts'
  - 'resources/js/pages/Store/Admin/Sales/Orders/Create.vue'
  - 'resources/js/pages/Store/Admin/Sales/Orders/Edit.vue'
  - 'app/Support/SearchOption.php'
  - 'app/Http/Controllers/Store/Admin/Concerns/SearchesOptions.php'
  - 'app/Http/Requests/Concerns/ValidatesSearchOptions.php'
  - 'app/Http/Requests/Store/Admin/Search*Request.php'
  - 'app/Http/Controllers/Store/Admin/{OrderEntryController,CampaignProductController,CouponController,NewsletterController,ProductSearchController,AddressController,TripController,CustomerNotificationController,OrderConfigurationController,ReportController,ProductController,CustomerController}.php'
---

# Any select that searches another table is a StoreSearchSelect

Added 2026-11-05. **A select whose options come from another table that can
grow (customers, representatives, products, brands, orders, sellers, cities, users)
uses `components/StoreSearchSelect.vue`, shaped like the warehouse product
search.** Do not hand-write a search input beside a native `<select>`, a
`<select multiple>` fed by a search, a result-button list, or a preloaded
`->get()` of the whole table. AI agents adding or changing such a select must
follow this file. A raw remote search outside the component fails
`resources/js/pages/Store/Admin/storeSearchSelectGuard.test.ts`.

A bounded reference list (country, role, status, unit, a store's own staff,
the category tree, the brands of a product form) stays a `StoreSelectField`
with its options sent by the page. If it grows past a few hundred rows, or the
page needs to search it, move it to a StoreSearchSelect.

## The component

- `v-model` holds the whole `SearchOption` (single) and `v-model:items` the
  array (`multiple`), so a label survives an edit form's prefill. Props:
  `id`, `label`, `endpoint` (a Wayfinder action, never a URL string), `name`,
  `params` (extra query, a dependent select's `state_id`), `multiple`,
  `required`, `disabled`, `clearable`, `error`, `placeholder`,
  `searchPlaceholder`, `helper`, `minChars`. Slots `option`, `image`, `badge`,
  `selected`, `empty`. Events `select`, `remove`, `clear`, `open`, `close`.
- Typeahead (300 ms debounce, stale responses dropped), infinite scroll,
  highlighted matches, arrow/Enter/Esc/Tab keys with `aria-activedescendant`,
  loading skeleton, empty, error + retry and minimum-characters states, clear
  button, chips for `multiple`. The mechanics are `lib/useSearchOptions.ts`
  (`useProductSearch` is a wrapper over it); do not copy them.
- The open list is a teleported `position: fixed` layer (`z-[70]`), anchored
  with `usePanelMaxWidth` + `usePanelViewportTracking`, flipped above when there
  is no room, a top-anchored sheet with a backdrop on a phone. Logical
  properties only (`ps-`, `pe-`, `start-`, `end-`, `border-s-2`); the physical
  rect is converted to `insetInlineStart` ([js-components.md](js-components.md)).
- **Forms.** A hidden `<input type="hidden" :name>` posts the id (`name[]` and
  one input per chip for `multiple`; a single select posts `''` when empty).
  The search box lives in the teleported panel, unnamed, so it is never posted
  and Enter cannot submit the form. With `required` the label shows
  `RequiredMark`, the trigger has `aria-required`, and an unnamed `sr-only`
  `required` input hands focus to the trigger for native validation
  ([form-required-fields.md](form-required-fields.md)). A pick dispatches a
  bubbling `change` from the hidden input so `useFormValidation` rechecks the
  field ([client-side-validation.md](client-side-validation.md)). The server
  error goes in `error`.
- Pages map `option.extra` to what they need after a pick with a typed alias
  (`StoreOrderProductExtra`), never by reading the row's display fields.

## The endpoint

- Return `{ data: SearchOption[], meta }` through `SearchesOptions::optionsPage()`
  (20 per page, `paginated()` shape) with rows from `App\Support\SearchOption`
  (`id`, `label`, `description`, `image`, `icon`, `badge`, optional `extra`).
- Validate with `ValidatesSearchOptions::searchOptionsRules()` (`q` max 100,
  `page` min 1) plus the endpoint's own parameters. Search with
  `whereWordsMatch()` (every word in some column, `%` and `_` escaped); never
  `'%'.$q.'%'` with a raw term. Select only the columns the option needs and
  eager load with column lists.
- **Order** with `orderByRelevance()`: names starting with the term, then name,
  then key. Typeaheads are not tables, so they are exceptions to newest first
  ([store-list-default-order.md](store-list-default-order.md)); a controller that
  `use`s `SearchesOptions` is named in that rule's exceptions table.
- Keep the route's own `permission:`. A new select for a page gets an endpoint
  guarded by that page's permission, not a wider shared one. A row shows contact
  data (phone, email) only where the page's staff already work with that
  customer. There is no `throttle` on sibling store admin routes, so none is added.
- An edit page sends its picked values in the same shape (`SearchOption::customer($user)`),
  never only an id.

## Result row per entity

Adding a select for a new entity adds its row here and a factory in
`App\Support\SearchOption`.

| Entity | image / lead | label | description | badge | `extra` |
|---|---|---|---|---|---|
| Customer (`orders.search-options` kind=customers, notification, trip) | initials | name | phone · email | `Account <AccSysID>` | `account_id`, `member_serial`, `rep_discount_percentage` |
| Representative (kind=representatives, order configuration) | initials | name | phone · email | `<n>% discount` (info) | `rep_discount_percentage` |
| Product (order form) | thumbnail, else package tile | name in the active language | `SKU · #id · unit price` | stock: `In stock N` (success), `Low N` (< 10, warning), `Out of stock` (danger); none for digital | `serial`, `mat_id`, `unit_price`, `unit_equal`, `tax`, `digital`, `stocks[]` |
| Campaign and coupon product | thumbnail, else package tile | name | `SKU · #id · price` | current discount (info) | `price`, `discount`, `discount_type` |
| Order configuration product | thumbnail, else package tile | name | `#id` | none | `mat_id` |
| Brand | tag tile | name | none | none | none |
| State | pin tile | name | country | none | `country_id` |
| City | pin tile | name in the active language | state | none | `state_id` |
| Trip checkpoint order | truck tile | `#code` | customer · total | none | none |
| Newsletter recipient | mail tile | email (the posted value) | name | none | none |
| Seller or report user | initials | name | email | none | none |

## Tests

A new or changed select has: a Vitest assertion that it renders its results,
shows its prefill, and posts the hidden input; a Pest test of the endpoint's
full payload, escaping, pagination past 20 rows with noise rows, and each
unauthorized caller; the `RequiredFieldComponents` table covers the component.
Browser-only behaviour (clipping, RTL, phone sheet, keys) is
`tests/e2e/store-search-select.spec.ts`; use `tests/e2e/support/searchSelect.ts`
to drive a select in other specs.
