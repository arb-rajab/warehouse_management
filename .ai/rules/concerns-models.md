---
paths:
  - 'app/Http/Requests/Concerns/FiltersCellStatusLogs.php,app/Models/CellStatusLog.php'
---

# Concerns Models

## CellStatusLog action filter is multi-select; sorting uses a correlated subquery
`action` is now `nullable|array` with `action.*` enum-validated (was a single nullable enum) — the admin/API listings send `action[]=x&action[]=y`. The `filtered()` scope reads it via `$request->enums('action', CellLogAction::class)` and `whereIn`, not `$request->string()`.

Sorting is a separate `#[Scope] sorted(Builder $query, Request $request)` on CellStatusLog, driven by `sort_by` (`created_at`|`expiration_date`, validated in the same FiltersCellStatusLogs trait) and `sort_direction` (`asc`|`desc`). Defaults to `created_at` desc (the old hardcoded `->latest()`) and always adds `orderBy('id', $direction)` as a tiebreaker for stable pagination. Both Admin and Api/V1 controllers call `->filtered($request)->sorted($request)` instead of `->latest()`.

Sorting by `expiration_date` orders by a correlated subquery (`Pallet::query()->select('expiration_date')->whereColumn('pallets.id', 'cell_status_logs.pallet_id')` passed straight to `orderBy()`) rather than a join — a join would collide with `cell_status_logs.id`/`created_at` etc. and require aliasing every column in `SELECT_COLUMNS`/`WITH_DETAILS`. Logs with no pallet sort as NULL (first on ASC, last on DESC, per SQLite/MySQL NULL-ordering).

Frontend: the admin Index.vue action filter uses the new `components/FilterMultiSelect.vue` (checkbox dropdown) instead of `FilterSelect.vue`; `filters.action` is a `string[]`. Sorting is wired through `DataTable.vue`'s sortable-header support (`columns` entries can be `{ label, sortKey }`, plus a `sort` prop and a `sort` emit) — clicking a header calls `toggleSort()` in Index.vue, which applies immediately (no "Apply filters" click needed), defaulting to ascending on a newly-clicked column and flipping on repeat clicks of the same column.

## product_id and user_id are multi-select array filters, not single ids
Like `action`, both `product_id` and `user_id` are now `nullable|array` with `product_id.*`/`user_id.*` integer-validated (was a single nullable `exists:...,id` integer for each). `CellStatusLog::filtered()` reads them via `whereIn('product_id'/'user_id', array_map('intval', $request->array(...)))`, not `$request->integer()`/`where()`. The admin Index.vue filter fields for product and user use FilterMultiSelect (not FilterSelect); `filters.product_id`/`filters.user_id` are `string[]`.
