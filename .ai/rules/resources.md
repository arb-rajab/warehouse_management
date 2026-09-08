---
paths:
  - 'app/Http/Resources/**'
  - app/Http/Resources/RowResource.php
---

# Resources

## Resource conventions: @property-read docblocks, whenLoaded, nested Resources
Every Resource documents the model's exposed shape with a `@property-read` PHPDoc block above the class instead of typed properties. Conditional/relation fields use `whenLoaded('relation', fn () => ...)`; nested resources are composed by instantiating another Resource inline (`new ProductResource($this->product)`), not by merging `->toArray()`. For a couple of fields, a plain inline array is fine instead of a full Resource class (see the pallet summary in CellResource). The id/name user summary is the exception that outgrew that: it is `UserSummaryResource`, shared by CellStatusLogResource (both `user` and a flag's `acknowledged_by`), CellVerificationReportResource and CellVerificationRoundResource — deliberately narrower than UserResource, which also exposes the email and admin flag. `JsonResource::withoutWrapping()` is set globally in AppServiceProvider — never add a `data` wrapper. Enums are always unwrapped (`->value`); Carbon dates use `->toDateString()` for date-only fields and `->toIso8601String()` for timestamps.

## RowResource.has_pallets must be supplied by the caller — there is no fallback
RowResource reads `has_pallets` straight off the model. Every caller supplies it: `Row::withHasPallets()` (a scope) when querying, or `Row::loadHasPallets()` for a model route-model binding already resolved. There is deliberately no fallback to `$row->hasPallets()`.

The resource used to fall back to that method when the attribute was absent, which read as a safe default but is a query every time (`cells()->whereHas('pallet')->exists()`), even when `cells` is already eager-loaded. On the unpaginated `GET /api/v1/rows/full` that meant one exists query per row — an N+1 that the fallback's existence hid, since every caller "worked correctly". Removing it trades that hidden cost for a visible contract, but note how a violation actually presents: this app does not enable `Model::preventAccessingMissingAttributes()`, so a caller that forgets the subquery does not throw — the missing attribute reads as `null` and the row is reported `has_pallets: false`, which on the rows index would silently offer a delete button for a row that holds pallets. The guard is therefore the tests, not the runtime: `Api\V1\RowController::index` and `full()` each assert no per-row exists query, and the admin show/edit tests assert the flag's actual value. Add the same kind of assertion alongside any new RowResource caller. Enabling strict mode app-wide would make this fail loudly for real, and is worth doing separately.

`Row::hasPallets()` still exists for `RowController::lockedRowHasPallets()`, which needs the live check inside a locking transaction rather than a value snapshotted at query time — do not route that through the resource's attribute.
