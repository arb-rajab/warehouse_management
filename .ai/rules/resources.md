---
paths:
  - 'app/Http/Resources/**'
  - app/Http/Resources/RowResource.php
---

# Resources

## Resource conventions: @property-read docblocks, whenLoaded, nested Resources
Every Resource documents the model's exposed shape with a `@property-read` PHPDoc block above the class instead of typed properties. Conditional/relation fields use `whenLoaded('relation', fn () => ...)`; nested resources are composed by instantiating another Resource inline (`new ProductResource($this->product)`), not by merging `->toArray()`. For a couple of fields, a plain inline array is fine instead of a full Resource class (see the pallet/user summaries in CellResource/CellStatusLogResource). `JsonResource::withoutWrapping()` is set globally in AppServiceProvider — never add a `data` wrapper. Enums are always unwrapped (`->value`); Carbon dates use `->toDateString()` for date-only fields and `->toIso8601String()` for timestamps.

## RowResource.has_pallets prefers a withExists() alias over calling hasPallets()
RowResource::toArray() checks array_key_exists('has_pallets', $this->resource->getAttributes()) first, and only falls back to calling $this->resource->hasPallets() if that query-time alias wasn't loaded. RowController::index() sets it via ->withExists(['cells as has_pallets' => fn ($q) => $q->has('pallet')]) so listing a page of rows costs one query instead of one hasPallets() exists-query per row (N+1). Any new caller of RowResource that doesn't eager-load the withExists alias still works correctly (falls back to the method), just without the optimization — but a paginated index listing rows should always add the withExists() clause rather than relying on the fallback.
