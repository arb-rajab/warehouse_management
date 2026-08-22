---
paths:
  - 'tests/e2e/**'
---

# E2E

## Shared WCAG contrast-ratio helper lives in tests/e2e/support/contrast.ts
`relativeLuminance`/`contrastRatio` (WCAG contrast math) live in `tests/e2e/support/contrast.ts`, shared by `login-dark-mode.spec.ts` and `admin-cells-map-3d-dark-mode.spec.ts` — extracted once the second dark-mode contrast spec needed it. Don't re-inline these functions in a new spec; import them. The in-browser `toRgb` (CSS color string → [r,g,b] via a 1x1 canvas) still has to stay inline inside each spec's `page.evaluate`/`el.evaluate` callback — Playwright serializes that callback to run in the browser, so it can't close over an imported Node-side function.
