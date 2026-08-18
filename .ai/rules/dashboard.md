---
paths:
  - resources/js/pages/Admin/Dashboard/Index.vue
---

# Dashboard

## Dashboard's custom-expiring-days input has a visible label, not just aria-label
The `#dashboard-custom-expiring-days` input has a visible `<label>` (styled with `fieldLabelClass` from lib/filters.ts) as a sibling right before it — it was the one numeric filter-like input in the app with only an `aria-label` and no on-screen label. Keep the label as a direct sibling of the input inside the existing outer card div (don't add a new wrapping div around just label+input) — Dashboard/Index.test.ts's custom-expiring-soon test does `wrapper.find('#dashboard-custom-expiring-days').element.closest('div')` and expects that to still be the outer card div containing the sibling `<a>` link.
