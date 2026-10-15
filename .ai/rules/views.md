---
paths:
  - 'resources/views/**'
---

# Views

## Dark mode is prefers-color-scheme only
No `$appearance` prop is shared to any view, and `resources/css/app.css` defines no `@custom-variant dark`, so every `dark:` utility resolves to the `prefers-color-scheme` media query. The starter kit's `@class(['dark' => ($appearance ?? 'system') == 'dark'])` on the `<html>` tag was removed because nothing populated `$appearance` — do not re-add it without also adding the middleware that shares the value. `tests/e2e/login-dark-mode.spec.ts` covers dark mode via Playwright's `emulateMedia({ colorScheme: 'dark' })`.

## User-written HTML is printed with clean_html(), never raw
A product, classified or digital-product description, support-ticket details or replies, and anything else a seller or customer writes as HTML, is printed as `{!! clean_html($x) !!}` (App\Services\HtmlSanitizer). A raw `{!! !!}`, `@php echo` or `<?php echo` of such a field is stored XSS: the admin ticket page ran a customer's script in a staff session. Admin-written content (CMS pages, settings texts) is trusted and printed as it is. The converted writers clean it before storing it instead: blog posts, whose public page also prints `clean_html($blog->description)` (store-backoffice-migration.md §18), and the footer, appearance and custom-page rich text, whose raw storefront prints (cookie text, custom and policy pages) are unchanged (§20). `HtmlSanitizerTest`'s scan fails on a new raw print of `->details`, `->reply`, `->description` or `getTranslation('description')` that is not wrapped.

