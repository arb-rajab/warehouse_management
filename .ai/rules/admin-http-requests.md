---
paths:
  - 'app/Http/Controllers/Admin/RowController.php,app/Http/Requests/Admin/UpdateRowRequest.php'
---

# Admin Http Requests

## Row resize/delete pallet-conflict check moved to controller inside a locked transaction
The "row has pallets" guard for resize (update) and delete used to live in UpdateRowRequest::withValidator() (validation-time, no locking). It's now in RowController::update()/destroy(), each wrapped in DB::transaction(...) with `$row->cells()->lockForUpdate()->get()` before checking hasPallets(), then update()/delete(). This closes a race where PalletController::store() could insert a pallet between the check and the write. This is a deliberate, narrow exception to controllers.md's "PalletController::lockCell() is the only place that should lockForUpdate()" note — RowController is now a second, legitimate lockForUpdate() caller for this specific race. Don't move the check back into the FormRequest without re-adding the lock.


## Row resize only blocks removing occupied cells
RowController::update() no longer blocks every dimension change on a row with pallets. Only a *shrink* is checked, and only against the cells it would delete (`Row::hasPalletsBeyond()`/`hasHistoryBeyond()`, columns -> `cells_count` error, levels -> `flats_count` error, still under the same `lockForUpdate()` transaction). Growing, or shrinking over empty history-free cells, is allowed. RowObserver::updated() therefore adds/removes only the cells outside the old/new bounds instead of regenerating the grid, so surviving cells keep their ids, pallets and history.
