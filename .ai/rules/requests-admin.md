---
paths:
  - 'app/Http/Requests/Admin/{FilterProductsRequest,ShowCellMapRequest}.php'
---

# Requests Admin

## expires_within_days never accepts 0; use the expired filter instead
`expires_within_days` is validated `min:1` everywhere it appears (FilterProductsRequest, ShowCellMapRequest, FiltersCellStatusLogs), matching FilterNumberField.vue's hardcoded HTML5 `min="1"`. Do not loosen this to `min:0` — "expiring today/already expired" is deliberately the `expired` boolean filter's job, not this field's. Keep the two concepts separate rather than letting one field cover both.
