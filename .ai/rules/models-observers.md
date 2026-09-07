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

## Auto-flagging makes any test that asserts on flags clock-dependent — pin the clock
The OffHours rule flags any log whose `created_at` falls outside config('cell_status_log_flags.off_hours') (06:00-22:00, app timezone UTC), and CellStatusLogFactory leaves `created_at` at now(). So a plain `CellStatusLog::factory()->create()` silently carries an extra OffHours flag whenever the suite runs in the evening or early morning — five tests across tests/Feature/Models/CellStatusLogTest.php and tests/Feature/Admin/CellStatusLogControllerTest.php failed exactly this way on a 03:20 UTC CI run, and passed again during the day.

Any test that asserts a flag count, asserts a log is unflagged, or filters by `flagged` must pin the clock inside working hours first — `Carbon::setTestNow('2026-08-01 12:00:00')`, the same pattern tests/Feature/Observers/CellStatusLogObserverTest.php already uses for every rule it exercises (Laravel's TestCase resets it in tearDown). Note this also stops those tests passing for the wrong reason: without the pin, a test asserting `flagged` is *true* passes at night even if the flag it created was never written.

`backdate()` does not help here — the observer runs on `created` and reads `created_at` as it was at insert time, before backdate() can move it.

Tests that scope their assertion to one reason (`->where('reason', CellLogFlagReason::QuickFlip)->exists()`) are unaffected, which is why the API PalletController tests stayed green.

