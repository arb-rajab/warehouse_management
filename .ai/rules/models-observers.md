---
paths:
  - 'app/Models/CellStatusLog.php,app/Observers/CellStatusLogObserver.php,config/cell_status_log_flags.php'
---

# Models Observers

## Rule-based auto-flagging lives in CellStatusLogObserver, hooked on CellStatusLog::created
CellStatusLog::create() (the single choke point in PalletController::logCellStatus()) triggers CellStatusLogObserver::created(), which evaluates the RapidActions/OffHours/QuickFlip rules from config/cell_status_log_flags.php and writes zero or more CellStatusLogFlag rows via $log->flags()->create(). This avoids touching PalletController at all and avoided introducing a new app/Services directory (none existed).

CellStatusLog::flagged is a computed Attribute following the exact RowResource.has_pallets pattern: prefers a `flags_count` alias (withCount), then an already-loaded `flags` relation, then falls back to a dedicated exists() query. WITH_DETAILS eager-loads `flags` (id, cell_status_log_id, reason, acknowledged_at) since the admin UI shows reasons on hover — don't switch it to withCount unless the eager-loaded reasons stop being needed there.

Flags are rule-based only (no manual/admin-created flags) but ARE acknowledgeable: CellStatusLogFlag has acknowledged_at/acknowledged_by (nullOnDelete on the user FK), set via Admin\CellStatusLogController::acknowledgeFlags() (POST admin/cell-logs/{cellStatusLog}/acknowledge-flags), which bulk-acknowledges every unacknowledged flag on that log. The `flagged` filter (whereHas('flags')) does NOT consider acknowledgment — a fully-acknowledged log still counts as flagged for filtering purposes; only the UI's Acknowledge button visibility depends on acknowledgment state.

Rule evaluation is forward-only: no backfill command exists for historical logs, by design.
