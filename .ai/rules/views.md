---
paths:
  - 'resources/views/**'
---

# Views

## Dark mode is prefers-color-scheme only
No `$appearance` prop is shared to any view, and `resources/css/app.css` defines no `@custom-variant dark`, so every `dark:` utility resolves to the `prefers-color-scheme` media query. The starter kit's `@class(['dark' => ($appearance ?? 'system') == 'dark'])` on the `<html>` tag was removed because nothing populated `$appearance` — do not re-add it without also adding the middleware that shares the value. `tests/e2e/login-dark-mode.spec.ts` covers dark mode via Playwright's `emulateMedia({ colorScheme: 'dark' })`.
