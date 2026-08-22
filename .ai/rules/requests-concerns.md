---
paths:
  - app/Http/Requests/Concerns/NormalizesBooleanFilters.php
  - app/Http/Requests/Concerns/NormalizesExpiredFilter.php
  - app/Http/Requests/Concerns/FiltersCellStatusLogs.php
---

# Requests Concerns

## NormalizesBooleanFilters is the generic GET-boolean normalizer; NormalizesExpiredFilter wraps it
`App\Http\Requests\Concerns\NormalizesBooleanFilters::normalizeBooleanFilter(string $field)` normalizes a GET-query boolean field ("true"/"false" query-string literals, which Laravel's `boolean` validation rule otherwise rejects) before validation. `NormalizesExpiredFilter` wraps it for the common `expired` field (`normalizeExpiredFilter()` calls `normalizeBooleanFilter('expired')`) — used by `FilterProductsRequest` and `ShowCellMapRequest`.

FiltersCellStatusLogs (shared by Admin\FilterCellStatusLogsRequest and Api\V1\FilterCellStatusLogsRequest) defines prepareForValidation() itself, normalizing 'flagged' via the same trait — since both consuming FormRequest classes only `use FiltersCellStatusLogs;` with no other prepareForValidation override, defining it in the trait covers both without duplication.

Add any future GET-boolean filter via `normalizeBooleanFilter($field)` directly, don't hand-roll the `filter_var`/`merge` dance again.
