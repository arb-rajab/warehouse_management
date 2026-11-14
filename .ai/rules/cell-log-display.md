---
paths:
  - 'resources/js/pages/Admin/CellStatusLogs/**'
  - 'resources/js/pages/Admin/Users/Show.vue'
  - 'resources/js/lib/cellStatusLogDisplay.ts'
  - 'resources/js/components/CellStatusLogRowCells.vue'
---

# Cell-log transfer rendering and shared row cells

## Merge transfers only for display

A transfer writes two real rows in one transaction: `transferred_out` at the
source and `transferred_in` at the destination. Both are required for per-cell
history and `attachNextLogs()` duration calculation (see [models](models.md)).
Never merge them in the database or resource layer.

`mergeTransferPairs()` in `cellStatusLogDisplay.ts` merges only when both sides
are present in the supplied page: same non-null pallet ID and timestamp,
mirrored cell/related-cell locations. It retains the outgoing fields plus
`pairedIn`, rendering one “Transferred” action and the pallet's `from_state`.
The duration/next-log values coincide because `attachNextLogs()` skips the
incoming sibling after `TransferredOut`. One-sided cell/action filters and
pagination boundaries naturally remain unmerged; add no special cases.

## Reuse display helpers and the row-cell fragment

Both listings import `mergeTransferPairs`, `transferPair`, `cellLogActionLabel`,
`flagReasonLabel`, `DisplayCellStatusLog`, and flag acknowledgement helpers from
`cellStatusLogDisplay.ts`. State labels come from
[cell-state visuals](cell-state-visuals.md), never a duplicate lookup.

Both pages render `CellStatusLogRowCells.vue`. This is an intentional narrow
exception to the general single-root convention: sibling `<td>` fragments are
valid inside `DataTable`'s `<tr>`, while a wrapper is not. Vue 3 supports the
fragment, but it has no attribute fallthrough target: pass declared props/events,
never `class`, `style`, or other non-prop attributes; style the cells internally.

User history omits the redundant “Done By” column and passes `returnTo="user"`
to flag acknowledgement. The shared handler posts `return_to` so the backend
redirects explicitly to the right page; Referer/session history is unreliable.
Extend the shared component/helper rather than reimplementing either in a new
consumer. See [user history pagination](user-activity-history.md).

## Warehouse name under the slot

A slot letter repeats across warehouses and pallets can be transferred between
them, so a cross-warehouse transfer would look like a local move. When the system
has more than one warehouse, `CellStatusLogRowCells` names each cell's warehouse
in small muted text under its slot (`cellWarehouseName()` in
`cellStatusLogDisplay.ts`, `data-testid="cell-warehouse"`), on BOTH sides of a
transfer, including a same-warehouse transfer (never collapsed to one label).
A single-warehouse system renders nothing extra.

- The flag is the `multipleWarehouses` page prop, `Warehouse::hasMultiple()` (two
  ids at most, once per page, never per row). Both consumers, `CellStatusLogs/Index`
  and `Users/Show`, pass it as `:multiple-warehouses`; any new consumer must too.
  There is no separate pallet-history dialog: it is the log list filtered by pallet.
- The name comes from the cell's `warehouse` in the resource
  (`toLocationWithWarehouseArray`); every listing must eager-load
  `CellStatusLog::WITH_WAREHOUSES` (a Pest test asserts a constant query count).
- `sameCellLocation()` also compares the warehouse id, so equal letters in two
  warehouses never merge unrelated rows. Layout: each side is a `min-w-0` block
  that truncates with a `title`; the pair wraps on narrow screens and the arrow
  keeps `rtl:rotate-180`.

## Record corrections and cell-less entries

`note_changed` and `product_changed` are record corrections
(`isPalletCorrection()`). `CellStatusLogRowCells` shows "Not placed yet"
instead of a slot when `cell` is null (a correction of an unplaced pallet;
`transferPair()` then returns a null `from`), no state line when the states
are null, "Was: <old product>" under the product, and in the note column the
`reason`, the pallet note change (`old_note` → `new_note`, "none" for null;
only on `note_changed` or when either is set) and any action note. See
[pallet corrections](pallet-corrections.md).

