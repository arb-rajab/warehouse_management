---
paths:
  - 'resources/js/pages/Admin/CellStatusLogs/**'
---

# Cell Status Logs

## CellStatusLogs Index merges transfer pairs for display only
A transfer creates two real DB rows (`transferred_out` on the source cell, `transferred_in` on the destination), each needed for per-cell history and `attachNextLogs()`'s duration calc — see models.md. Do not merge them in the DB/resource layer.

In `Index.vue`, `displayLogs` (a computed) merges a `transferred_out` row with its `transferred_in` sibling into one row *only* when both are present in the current page's `logs.data` — matched via `isTransferPair()` (same `pallet_id`, same `created_at`, mirrored `cell`/`related_cell`). Every column besides the action label is already identical between the pair (confirmed: `duration_seconds`/`next_log_at` land on the same value for both sides because of the TransferredOut index+2 skip in `attachNextLogs()`), so the merged row just reuses the `transferred_out` row's fields and shows a single "Transferred" action label + one state (`from_state`, the pallet's condition) instead of a from→to arrow.

Filtering to one cell, one action, or a pallet-history page split across a pagination boundary naturally yields only one side of the pair, so those cases fall through unmerged with no special-casing needed — don't add any.
