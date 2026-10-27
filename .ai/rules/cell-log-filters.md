---
paths:
  - 'app/Http/Requests/Concerns/FiltersCellStatusLogs.php'
  - 'app/Http/Requests/{Admin,Api/V1}/FilterCellStatusLogsRequest.php'
  - 'app/Models/CellStatusLog.php'
  - 'app/Http/Controllers/{Admin,Api/V1}/CellStatusLogController.php'
  - 'resources/js/pages/Admin/CellStatusLogs/**'
---

# Cell-log filtering, sorting and filter UI

## Shared query and validation

Admin and API listings share `FiltersCellStatusLogs::cellStatusLogFilterRules()`,
`CellStatusLog::filtered($request)->sorted($request)`, and the model's listing
column/eager-load shapes. Extend these for another listing rather than copying
its filter chain. `filtered()` delegates action, user and creation-date filters to
`matchingHistoryFilters()`; product IDs are applied directly. These scopes read
request accessors, skipping absent filters.

`action`, `product_id` and `user_id` are nullable arrays: validate each action
against `CellLogAction` and each ID as an integer. Query with `whereIn`;
actions come from `$request->enums('action', CellLogAction::class)`, IDs from
integer-mapped request arrays. Admin action/user fields use `FilterMultiSelect`;
product fields use searchable `FilterProductSelect` and selected-label hydration
(see [product filters](product-filters.md)). Their form values are string arrays.

The `flagged` query parameter is normalized as a GET boolean. Requests neutralize
it to `false` for non-admin viewers: filtering by flag inclusion would expose
anti-fraud state even when the resource omits the flag fields. Keep this at the
request boundary; the model scope remains a function of filter parameters.
`pallet_id` intentionally has no `exists:pallets,id` rule because logs survive a
pallet's deletion.

## Date alternatives are mutually exclusive

`FiltersByDateRange::dateRangeFilterRules()`, composed by the cell-log trait,
makes `created_within_days` (`nullable|integer|min:1`) prohibit `date_from,date_to`.
It applies `whereDate('created_at', '>=', now()->subDays($days))`, without an
upper bound. Sending both modes is a validation error, never an implicit AND.

`expires_within_days` has the same validation and prohibits
`expiration_date_from,expiration_date_to`. Expiration filters share one
`whereHas('pallet', ...)`; the relative window applies
`expiration_date <= now()->addDays($days)`. The explicit lower bound starts at
`startOfDay()`; keep the existing upper-bound timestamp semantics.

`createdDateRangeExclusivity()` supplies `DateRangeFilterFields`' creation-date
disabled states; `exclusivePair()` handles the expiration alternatives.
The UI disables the *empty alternative* while the other mode has a value;
the active fields stay editable so they can be cleared to switch modes.
Native number inputs with plain `v-model` cast user input to numbers even when
initial state is a string: tests must allow `string | number`.

## Sorting stays stable across pages

`sort_by` accepts `created_at|expiration_date`, `sort_direction` accepts
`asc|desc` through the composed `SortsByDirection` trait; defaults are creation
time descending. Always add `id` in the same
direction as a tie-breaker. Expiration sorting uses a correlated pallet subquery,
not a join that would collide with listing columns. Missing pallets sort as
NULL (first ascending, last descending on SQLite/MySQL).

`DataTable` sortable headers call `toggleSort()` immediately: a new column starts
ascending and repeat clicks flip direction, without waiting for Apply.

## Main dialog and column popovers

The main `FilterDialog` starts closed. Tests must open it before querying its
`filter-*` fields. Apply submits and closes; Clear resets fields and leaves it
open. The badge counts active *concepts*, grouping each date-range/relative-days
alternative as one. The pallet-history banner and its Clear button stay outside.

Column popovers reuse the same filter state with `popover-*` IDs and apply on
change through `useColumnFilterPopover`; edits in the main dialog wait for Apply.
See [shared table components](js-components.md) and [filter dialogs](filter-dialogs.md).
