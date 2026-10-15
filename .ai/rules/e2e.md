---
paths:
  - 'tests/e2e/**'
---

# E2E

## Enforced CSP browser tests use local HTTPS

The default Playwright URL is `https://127.0.0.1:8010`. The harness starts
`tests/e2e/support/serve-https.mjs`, which generates a temporary certificate
with OpenSSL and proxies to a session-owned PHP server on the next port.
Only the loopback proxy is trusted. Its certificate and server processes are
removed on shutdown. Keep CSP enforced: `upgrade-insecure-requests` upgrades
Inertia redirects, so a plain HTTP PHP server cannot verify these workflows.
Do not disable that directive or weaken assertions to make CI pass.

## Shared WCAG contrast-ratio helper lives in tests/e2e/support/contrast.ts
`relativeLuminance`/`contrastRatio` (WCAG contrast math) live in `tests/e2e/support/contrast.ts`, shared by `login-dark-mode.spec.ts` and `admin-cells-map-3d-dark-mode.spec.ts` — extracted once the second dark-mode contrast spec needed it. Don't re-inline these functions in a new spec; import them. The in-browser `toRgb` (CSS color string → [r,g,b] via a 1x1 canvas) still has to stay inline inside each spec's `page.evaluate`/`el.evaluate` callback — Playwright serializes that callback to run in the browser, so it can't close over an imported Node-side function.

## The language switcher and logout button live behind AccountMenu — open it first
Since AdminLayout.vue moved the desktop language switcher and logout action into `AccountMenu.vue`'s dropdown panel (see layouts.md), `getByRole('button', { name: 'Arabic' })` / `{ name: 'English' }` / `{ name: 'Log out' }` are not visible/clickable until the account trigger (`getByRole('button', { name: 'Account' })`) is opened. `tests/e2e/support/nav.ts` exports `openAccountMenu(page)` for this — call it immediately before interacting with any control inside the panel. If the panel closes after an action (AccountMenu closes itself on every click inside it, including a dismissed logout confirm), call `openAccountMenu(page)` again before the next interaction with a panel control, same as `admin-logout-confirmation.spec.ts` does for its second logout click.

Use `switchLocale(page, label)` from that helper for locale buttons. It waits
for LanguageSwitcher's full-page reload before another navigation starts;
checking `dir` alone can race the reload and cancel the next visit.

## Shared editor and CSP helpers live in tests/e2e/support/editor.ts
Specs that drive the shared Tiptap editor (`StoreRichTextField`) import `watchCsp`/`documentViolations`/`expectStrictPolicy` (enforced strict policy, no violations), `uploadPhoto`, `press(page, key, editorSelector)` (waits for the editor to take in the selection), `focusedToolbarItem`/`arrowTo` and `colorsOf` from there: `store-blog-editor.spec.ts`, `store-newsletter-ticket.spec.ts` and `store-website-settings.spec.ts`. Don't re-inline them in a new spec. `pickUploads(page, id, files)` (editor.ts) picks library files for a canonical `StoreUploadField`, and `storedProduct(name, expression)` (storeFixtures.ts) reads a product back from the browser database; the digital and physical product form specs share both. The webServer env pins `MAIL_MAILER=array` (no spec can deliver mail) and a placeholder `MAIL_USERNAME` that only satisfies the newsletter's SMTP guard.
