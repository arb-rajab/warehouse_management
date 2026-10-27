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
