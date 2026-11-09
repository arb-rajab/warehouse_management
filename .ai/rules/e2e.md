---
paths:
  - 'tests/e2e/**'
---

# Browser tests for HTTPS, accessibility and editor behavior

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

## Language switcher and logout are directly visible in the back-office shell
Both layouts render through `BackofficeShell` (layouts.md). From 640 px the language buttons (`getByRole('button', { name: 'Arabic' })`) are in the header; from `lg` the logout button (`getByRole('button', { name: 'Log out' })`, an icon button with an `sr-only` label) is in the sidebar footer. No menu has to be opened first. On a phone both are inside the sidebar drawer: click "Open menu" (`button[aria-controls="admin-sidebar"]` or `"store-admin-sidebar"`) before using them. Names that occur twice ("Help" in the header and the sidebar, "Dashboard" as a link and a heading) are looked up inside the named landmark, `getByRole('navigation', { name: 'Warehouse menu' })` (store: "Store admin menu"), or inside `#admin-sidebar`. The warehouse map starts with the sidebar folded to its icon rail; link names are still the labels (`sr-only` text).

Use `switchLocale(page, label)` from that helper for locale buttons. It waits
for LanguageSwitcher's full-page reload before another navigation starts;
checking `dir` alone can race the reload and cancel the next visit.

## Select by role, name or test id, not by a palette class
The back office has no raw palette classes any more (js.md "Back-office colours"), so a spec must not locate an element by one: `admin-pagination-dark-mode.spec.ts` finds the active page link by `nav .bg-brand` (the shared `selectedToggleClass`), the sign-in frame by `data-testid` `auth-logo`/`auth-brand`/`auth-area`. Computed colours (contrast, `toHaveCSS`) are fine and are how dark mode is asserted; a colour a spec compares to a literal RGB is a token value (`--surface` in dark is `rgb(30, 26, 23)`, see `store-search-select.spec.ts`), so update it with the token, not the other way round. Native controls follow dark mode (`html` `color-scheme: dark`, asserted in `backoffice-shell.spec.ts`).

## Shared editor and CSP helpers live in tests/e2e/support/editor.ts
Specs that drive the shared Tiptap editor (`StoreRichTextField`) import `watchCsp`/`documentViolations`/`expectStrictPolicy` (enforced strict policy, no violations), `uploadPhoto`, `press(page, key, editorSelector)` (waits for the editor to take in the selection), `focusedToolbarItem`/`arrowTo` and `colorsOf` from there: `store-blog-editor.spec.ts`, `store-newsletter-ticket.spec.ts` and `store-website-settings.spec.ts`. Don't re-inline them in a new spec. `pickUploads(page, id, files)` (editor.ts) picks library files for a canonical `StoreUploadField`, and `storedProduct(name, expression)` (storeFixtures.ts) reads a product back from the browser database; the digital and physical product form specs share both. The webServer env pins `MAIL_MAILER=array` (no spec can deliver mail) and a placeholder `MAIL_USERNAME` that only satisfies the newsletter's SMTP guard.

Before selecting text for keyboard toolbar formatting, use
`press(page, key, editorSelector, expectedText)`: it waits for editor focus and
typed Tiptap content, then polls for the exact text in both the browser and
editor selections, with matching positions inside that editor. Matching positions
alone can describe an empty caret. Check the intended toolbar item's focus before
activation and retain the formatting/`aria-pressed` assertions afterward. Wait on
these real states with locator assertions or `expect.poll`; never add sleeps,
retries or longer timeouts to hide editor timing failures. Named upload fixtures
must replace their earlier copies so repeated runs keep strict locators unique.
