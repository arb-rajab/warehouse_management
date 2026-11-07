---
paths:
  - 'resources/js/pages/Store/Admin/**/Index.vue'
  - 'resources/js/components/StoreListFilters.vue'
  - 'resources/js/components/StoreListFilters.test.ts'
  - 'resources/js/components/StoreSelectField.vue'
  - 'resources/js/components/DataTable.vue'
  - 'tests/e2e/store-list-filter-chips.spec.ts'
---

# A store list filter lives in one place

Never render the same filter both at the top of a table and in the Filters dialog. This covers two different controls that set the same filter, such as a nav link and a checkbox, even when their query keys differ. The compact split itself (search plus at most one key filter, everything else in the dialog) is in [store-admin-list-filters.md](store-admin-list-filters.md); this rule decides which side a filter belongs on.

## Where each kind lives
- **Top of the table (the inline slot):** text search, and the one primary status switch or key filter staff use most (status tabs, seller, category, delivery status). At most one key filter besides search.
- **Filters dialog only (`#filters` slot):** everything else — secondary statuses, flags, date ranges, sort, approval and similar. Count each one in `activeCount`; never count search or the inline key filter.
- **Neither:** a link that navigates to another list is not a filter of this list. Do not put it in the filter area, and do not repeat it as a filter.

## Applied dialog filters show as removable chips
`StoreListFilters` renders one chip per applied Filters-dialog filter under the toolbar, and the Filters button's badge shows their number. So the chips are exactly the filters the badge counts: the dialog filters only, never search or the inline key filter.

- A page builds ONE computed `appliedFilters: StoreFilterChip[]` (`types/store.ts`) and passes it as `:chips`. Do not also compute a separate count: with `chips` the component counts `chips.length`, and `activeCount` is only for a page that has no chips yet.
- A chip is `{ key, label, value?, keys }`: the translated field label and value (a plain flag has no value), and the query keys removing it drops. A two-bound range (such as the Customers profit range) is one chip with both keys; an unrelated pair of dates is two chips.
- Removing a chip reloads the list from the address, keeping every other query parameter (search, key filter, `per_page`) and dropping `page`. A filter whose default differs from "absent" (Customers' Important, default Yes) chips only when set away from the default, and removing it restores the default.
- The trailing "Clear filters" link removes all chip keys and nothing else, like the dialog's Clear.
- A filter that gains or loses a dialog control gains or loses its chip in the same change. Chips are logical-property only (`ps-`/`pe-`) so they mirror in Arabic; `tests/e2e/store-list-filter-chips.spec.ts` covers the phone wrapping and the mirroring.

## When a filter changes sides
Remove it from the old place in the same change, drop it from `activeCount` where it no longer counts, and update the page test to assert it is absent there. A list may keep reading a legacy query key (for example when paging) without rendering a control for it.

## Current example
`Product/Products/Index.vue`: the all-products list has the `#product-presets` status links at the top and no State checkboxes; the in-house and seller lists have the State checkboxes in the dialog and no preset links. The audit of every list is in [../store-list-filter-audit-20261105.md](../store-list-filter-audit-20261105.md).
