---
paths:
  - 'app/Http/Requests/StoreRowRequest.php,app/Http/Requests/UpdateRowRequest.php'
---

# Http Requests

## Normalize Row.letter to uppercase in prepareForValidation()
StoreRowRequest/UpdateRowRequest uppercase `letter` via `Str::upper()` in `prepareForValidation()` before validation/persistence. Previously only the create/edit form's CSS (`input-class="uppercase"`) made it look uppercase, while the DB stored whatever case the user typed — a lowercase `a` would save as `a`, show lowercase in Rows/Index (no CSS there), and fail to match `CellController::resolveLocationSearch`'s `mb_strtoupper()` search. Keep normalizing server-side rather than relying on frontend CSS/display formatting alone.
