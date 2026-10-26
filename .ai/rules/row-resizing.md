---
paths:
  - 'app/Http/Controllers/Admin/RowController.php'
  - 'app/Http/Requests/Admin/UpdateRowRequest.php'
---

# Row resize and delete locking constraints

## Row resize/delete pallet-conflict check moved to controller inside a locked transaction
The "row has pallets" guard for resize (update) and delete used to live in UpdateRowRequest::withValidator() (validation-time, no locking). It now lives in RowController::update()/destroy() inside DB::transaction(...): shrinking locks the row's cells before checking the affected bounds, and deletion locks them through `lockedRowBlockReason()` before checking pallets/history. This closes a race where `PalletActionService::store()` could insert a pallet between the check and the write. These row operations own their cell locks; pallet actions use the service's shared locking and guard helpers (controllers.md). Don't move the check back into the FormRequest without retaining the locks.


## Row resize only blocks removing occupied cells
RowController::update() no longer blocks every dimension change on a row with pallets. Only a *shrink* is checked, and only against the cells it would delete (`Row::hasPalletsBeyond()`/`hasHistoryBeyond()`, columns -> `cells_count` error, levels -> `flats_count` error, still under the same `lockForUpdate()` transaction). Growing, or shrinking over empty history-free cells, is allowed. RowObserver::updated() therefore adds/removes only the cells outside the old/new bounds instead of regenerating the grid, so surviving cells keep their ids, pallets and history.
