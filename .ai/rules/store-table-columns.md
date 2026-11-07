---
paths:
  - 'resources/js/pages/Store/Admin/**/Index.vue'
  - 'resources/js/components/DataTable.vue'
  - 'resources/js/components/StoreColumnPicker.vue'
  - 'resources/js/components/StoreColumnPicker.test.ts'
  - 'resources/js/components/StoreProductReport.vue'
  - 'resources/js/lib/useTableColumns.ts'
  - 'resources/js/lib/useTableColumns.test.ts'
  - 'resources/js/lib/storeCustomerColumns.ts'
  - 'resources/js/testing/tableColumns.ts'
  - 'resources/js/pages/Store/Admin/tableColumns.test.ts'
---

# Every store list table lets users show and hide its optional columns

Introduced on 2026-11-05. A store back office table shows many columns, and which
ones matter differs between staff, so each user chooses. The choice is
remembered in the browser. The guard is `pages/Store/Admin/tableColumns.test.ts`.

## The rule

- **Every store list table** (a page under `resources/js/pages/Store/Admin/` or a
  shared `Store*.vue` component that renders a `DataTable` as a list) uses
  `lib/useTableColumns.ts` and `components/StoreColumnPicker.vue`. Do not write
  another column chooser, another storage key scheme or another toolbar button.
- **A new list adopts it in the same change**, and a change that adds, removes or
  renames a column updates the definitions, the header and the cell together.
  AI agents must follow this when they add or change a list.
- **The only lists without it** are those with nothing optional to choose (an
  identifier and the actions only: blog categories, roles, attribute values) and
  tables that are not lists (the lines of one order, rows inside a form editor).
  They are named, with the reason, in `tableColumns.test.ts`. A table that gains
  an optional column leaves that list and gets the picker.

## Definitions: `TableColumnDef`

One list of definitions per table, in table order:
`{ key, label, required?, defaultVisible?, available? }`.

- `label` is a translation key (the header and the picker's checkbox use it).
- `required: true` is the row's identifier (the name, the code, the title): it is
  always shown and locked in the picker. Every list has at least one. Pick the
  column that tells a human which record the row is; a pure row counter (`#`) or
  an id is not it unless the list has no better one.
- `defaultVisible` defaults to `true`: a list shows every column until the user
  chooses otherwise. Do not hide a column by default without being asked.
- `available: false` leaves a column out altogether (a permission, a list variant,
  a prop): it is neither offered nor shown. Pass the definitions as a getter
  (`columns: () => [...]`) when `available` reads a prop or `access`.
- Select (bulk-action checkboxes) and Actions are not definitions. They are always
  present, and the page puts them around the chosen columns.

## Header and cells come from one list

```ts
const columns = useTableColumns({ listId: 'sales.orders', columns: [...] });
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    t('store.orders.select'),                       // fixed leading columns, if any
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.orders.actions'),                      // fixed trailing columns
]);
```

- `DataTable :columns="tableColumns"`, and in the `#row` slot every optional cell is
  `<td v-if="shown.email">`. A required cell and the fixed Select/Actions cells have
  no `shown` check. A header without its cell (or the reverse) misaligns every row.
- A cell group made with `v-for` (the switch columns of a product list, the image
  columns of the categories) is a `<template v-for>` around a `<td v-if="shown[key]">`
  or iterates a computed filtered by `shown`; `v-if` on the `v-for` element cannot
  see the loop variable.
- Put `<StoreColumnPicker :state="columns" />` directly above the `DataTable`, below
  the filter form and the filter chips, as the table's one toolbar row. Bulk-action
  forms that sit apart from the table go in its default slot (start side); the
  Columns button is always on the end side. A form that wraps the table (orders,
  visits) keeps its place and the picker row goes above it. Do not wrap or move the
  `DataTable` itself; the layout contract is in [store-table-layout.md](store-table-layout.md).

## Storage

- Key: `store.tableColumns.<storeUserId>.<listId>` in `localStorage`, so two staff
  sharing a browser keep separate choices. The value is a JSON array of the
  visible keys. No server state.
- `listId` is `<area>.<list>` in lower camel case (`sales.orders`,
  `trips.checkpoints`, `product.products.seller`): stable, unique per table, and
  one per list variant when the variants have different columns (customers vs
  representatives; the in-house, all and seller product lists).
- Reading is safe: empty, junk, blocked or unavailable storage, and stale or
  unknown keys, fall back to the defaults or are ignored; required columns are
  always included; the order comes from the definitions. Writing swallows errors.
  Nothing is written until the user chooses.
- `legacyStorageKey` is only for the Customers list's old `store.customers.columns.*`
  key; do not use it for anything new.
- A column added later with `defaultVisible: true` does not appear for a user who
  already saved a choice (the stored list is of visible keys, like the Customers
  list always was). That is accepted.

## Look

- The button is the Customers one: `Columns3` icon, `filterTriggerButtonClass`, label
  `store.table.columns`, `ms-auto` (logical, so it mirrors in Arabic). The dialog is
  `FilterDialog` titled `store.table.columnsTitle`: a one/two-column checkbox grid,
  a required column ticked, disabled and marked `store.table.alwaysShown`, and the
  `store.table.showAll` and `store.table.reset` buttons.
- All five labels are `store.table.*` in `en.json` and `ar.json`. Per-column labels
  keep the list's own keys; `store.reports.number` is the `#` of the report tables.

## Tests

- `useTableColumns.test.ts` and `StoreColumnPicker.test.ts` cover the composable and the
  dialog. A page test hides one optional column with
  `expectColumnCanBeHidden(wrapper, listId, { key, label })` from `testing/tableColumns.ts`
  (the header loses it, every row loses its cell, header and cell counts still match,
  the choice is stored) and checks the required column with `expectColumnLocked`. Pass
  the test user's id when the page test signs in someone other than `defaultAuthProps()`.
- `pages/Store/Admin/tableColumns.test.ts` fails when a page renders a `DataTable`
  without `useTableColumns(`, `<StoreColumnPicker` and `:columns="tableColumns"` and is
  not on its skip list, when a skip entry has gone stale, when two lists share a
  `listId`, or when a picker page never tests `shown` in its template.
