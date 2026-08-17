---
paths:
  - 'app/Http/Middleware/**'
  - 'app/Http/Middleware/BlockMaliciousRequests.php,config/waf.php'
---

# Middleware

## Keep the shared auth prop narrow
`HandleInertiaRequests::share()` sends `$request->user()?->only(['id', 'name', 'email'])`, NOT the model. Sharing `$request->user()` directly puts every current and future `users` column on every page of the app — that is how password hashes and tokens leak into HTML.

The TS counterpart is `AuthUser` in `resources/js/types/auth.ts` (consumed via `Auth` in `types/global.d.ts`). Widen both sides together or not at all. `tests/Feature/SharedAuthPropTest.php` asserts the prop has exactly 3 keys and is null for guests, so it fails loudly if the model is shared again.

Note there are two distinct user types on the frontend on purpose: `AuthUser` (types/auth.ts, the shared signed-in user) and `User` (types/admin.ts, the UserResource shape with `is_admin`). Do not merge them.

## Application-level WAF: BlockMaliciousRequests
A global middleware (registered via `$middleware->append()` in bootstrap/app.php, so it runs before web/api group middleware on every request) blocks requests whose URI or query/body input matches a signature in config/waf.php (SQLi, XSS, path traversal, command injection). Toggle with WAF_ENABLED env var without a deploy. Add `config('waf.exclude_paths')` entries (wildcards via Request::is()) for any route that legitimately needs to accept payloads resembling these signatures, rather than weakening a pattern. Tests: tests/Feature/BlockMaliciousRequestsTest.php.
