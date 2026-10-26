---
paths:
  - 'app/Models/CellStatusLog.php'
  - 'app/Observers/CellStatusLogObserver.php'
  - 'config/cell_status_log_flags.php'
  - 'tests/Feature/Observers/CellStatusLogObserverTest.php'
---

# Cell-log auto-flagging, acknowledgement and clock-sensitive tests

## Forward-only automatic flags

`CellStatusLog::create()` triggers `CellStatusLogObserver::created()`, which
checks RapidActions/OffHours/QuickFlip from `config/cell_status_log_flags.php`
and creates zero or more `CellStatusLogFlag` rows. Keep this at the model event
boundary. Pallet logs originate in `PalletActionService::logCellStatus()`;
`Admin\CellController::toggleActive()` also creates logs. Callers do not duplicate
rule evaluation. There are no manually
created flags or historical backfill command, by design.

The `flagged` attribute prefers a `flags_count` alias, then an already-loaded
`flags` relation, then an `exists()` query (the `RowResource.has_pallets` pattern).
Admin listings load flag details for reason labels; do not replace those details
with a count while the UI needs them. Worker payloads omit anti-fraud details;
see the request boundary in [cell-log filtering](cell-log-filters.md).

`POST admin/cell-logs/{cellStatusLog}/acknowledge-flags` bulk-acknowledges every
unacknowledged flag on the log. `acknowledged_at` and `acknowledged_by` record it;
the user foreign key uses `nullOnDelete`. Acknowledgement does not affect the
`flagged` filter (`whereHas('flags')`): an acknowledged log is still flagged.
Only the Acknowledge button depends on unacknowledged flags.

## Pin the clock when asserting flags

OffHours uses the insert-time `created_at` and configured hours (06:00–22:00,
app timezone UTC). A factory's default `now()` can add an unexpected flag.
Before asserting a flag count, unflagged state or flagged filtering, pin working
hours, e.g. `Carbon::setTestNow('2026-08-01 12:00:00')`; Laravel resets it in
tearDown. Otherwise a positive assertion may also pass for the wrong reason.

`backdate()` runs after the observer and cannot prevent an insert-time flag.
Assertions scoped to one reason, such as `QuickFlip`, avoid unrelated OffHours
counts; `CellStatusLogObserverTest` demonstrates the clock-pinning pattern.
