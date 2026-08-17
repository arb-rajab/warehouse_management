---
paths:
  - 'app/Http/Middleware/**'
  - 'app/Http/Middleware/BlockMaliciousRequests.php,config/waf.php'
  - 'app/Http/Middleware/RestrictToAllowedIps.php,config/telescope.php,config/pulse.php'
---

# Middleware

## Keep the shared auth prop narrow
`HandleInertiaRequests::share()` sends `$request->user()?->only(['id', 'name', 'email'])`, NOT the model. Sharing `$request->user()` directly puts every current and future `users` column on every page of the app — that is how password hashes and tokens leak into HTML.

The TS counterpart is `AuthUser` in `resources/js/types/auth.ts` (consumed via `Auth` in `types/global.d.ts`). Widen both sides together or not at all. `tests/Feature/SharedAuthPropTest.php` asserts the prop has exactly 3 keys and is null for guests, so it fails loudly if the model is shared again.

Note there are two distinct user types on the frontend on purpose: `AuthUser` (types/auth.ts, the shared signed-in user) and `User` (types/admin.ts, the UserResource shape with `is_admin`). Do not merge them.

## Application-level WAF: BlockMaliciousRequests
A global middleware (registered via `$middleware->append()` in bootstrap/app.php, so it runs before web/api group middleware on every request) blocks requests whose URI or query/body input matches a signature in config/waf.php (SQLi, XSS, path traversal, command injection). Toggle with WAF_ENABLED env var without a deploy. Add `config('waf.exclude_paths')` entries (wildcards via Request::is()) for any route that legitimately needs to accept payloads resembling these signatures, rather than weakening a pattern. Tests: tests/Feature/BlockMaliciousRequestsTest.php.

## RestrictToAllowedIps is shared between Telescope and Pulse — pass the config key as a middleware param
Second layer of defense (on top of the viewTelescope/viewPulse gates) blocking internal-tool routes from IPs not in an allowlist. It's generic, not tool-specific: the allowed-ips config key is passed as a middleware parameter, e.g. RestrictToAllowedIps::class.':telescope.allowed_ips' and RestrictToAllowedIps::class.':pulse.allowed_ips'. Both tools' config middleware arrays reference this one class — don't fork a per-tool copy if a third internal tool needs the same protection; add another ':config.key' registration instead.
