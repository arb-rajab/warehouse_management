---
paths:
  - 'app/Http/Requests/StoreRowRequest.php,app/Http/Requests/UpdateRowRequest.php'
---

# Http Requests

## Normalize Row.letter to uppercase via the shared NormalizesRowLetter trait
StoreRowRequest/UpdateRowRequest both `use App\Http\Requests\Concerns\NormalizesRowLetter`, which uppercases `letter` via `Str::upper()` in `prepareForValidation()` before validation/persistence. Previously only the create/edit form's CSS (`input-class="uppercase"`) made it look uppercase, while the DB stored whatever case the user typed — a lowercase `a` would save as `a`, show lowercase in Rows/Index (no CSS there), and fail to match `CellController::resolveLocationSearch`'s `mb_strtoupper()` search. Keep normalizing server-side rather than relying on frontend CSS/display formatting alone. A third request needing this should use the same trait, not a re-inlined copy.
