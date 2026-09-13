---
paths:
  - 'tests/e2e/**'
---

# E2E

## Shared WCAG contrast-ratio helper lives in tests/e2e/support/contrast.ts
`relativeLuminance`/`contrastRatio` (WCAG contrast math) live in `tests/e2e/support/contrast.ts`, shared by `login-dark-mode.spec.ts` and `admin-cells-map-3d-dark-mode.spec.ts` — extracted once the second dark-mode contrast spec needed it. Don't re-inline these functions in a new spec; import them. The in-browser `toRgb` (CSS color string → [r,g,b] via a 1x1 canvas) still has to stay inline inside each spec's `page.evaluate`/`el.evaluate` callback — Playwright serializes that callback to run in the browser, so it can't close over an imported Node-side function.

## The language switcher and logout button live behind AccountMenu — open it first
Since AdminLayout.vue moved the desktop language switcher and logout action into `AccountMenu.vue`'s dropdown panel (see layouts.md), `getByRole('button', { name: 'Arabic' })` / `{ name: 'English' }` / `{ name: 'Log out' }` are not visible/clickable until the account trigger (`getByRole('button', { name: 'Account' })`) is opened. `tests/e2e/support/nav.ts` exports `openAccountMenu(page)` for this — call it immediately before interacting with any control inside the panel. If the panel closes after an action (AccountMenu closes itself on every click inside it, including a dismissed logout confirm), call `openAccountMenu(page)` again before the next interaction with a panel control, same as `admin-logout-confirmation.spec.ts` does for its second logout click.
