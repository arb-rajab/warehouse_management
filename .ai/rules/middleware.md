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
A global middleware (registered via `$middleware->append()` in bootstrap/app.php, so it runs before web/api group middleware on every request) blocks requests whose URI, inspected headers or query/body input matches a signature in config/waf.php (SQLi, XSS, path traversal, command injection). Toggle with WAF_ENABLED env var without a deploy. Add `config('waf.exclude_paths')` entries (wildcards via Request::is()) for any route that legitimately needs to accept payloads resembling these signatures, rather than weakening a pattern. Tests: tests/Feature/BlockMaliciousRequestsTest.php.

The middleware fails *closed*, and three details keep it that way — don't undo them:
- The inspection subject is built by flattening `$request->all()` with `Arr::dot()`, never `json_encode()`. json_encode returns `false` for any byte that isn't valid UTF-8, and the old `?: ''` fallback then dropped the entire body from the scan — one stray byte switched off every input check on the request.
- `preg_match()` returning `false` (PCRE gave up: backtrack limit blown by a padded payload, a `/u` pattern against invalid UTF-8) blocks the request, same as a match. Reading it as "no match" lets an attacker disable a check by making it expensive.
- The URI is scanned raw *and* percent-decoded twice, because `getRequestUri()` hands back the raw request line — `%2e%2e%2f` and `%252e%252e%252f` never match a pattern written against literal `../` otherwise.

`config('waf.inspect_headers')` is an allow-list (User-Agent, Referer) on purpose. Don't widen it to all headers: cookies and framework-issued tokens carry opaque payloads that only produce false positives.

SecureHeadersMiddleware is appended *before* BlockMaliciousRequests in bootstrap/app.php so it wraps it. The WAF aborts with a 403 from inside the pipeline, so anything appended after it never runs on a blocked request and those responses would ship with no security headers.

## RestrictToAllowedIps is shared between Telescope and Pulse — pass the config key as a middleware param
Second layer of defense (on top of the viewTelescope/viewPulse gates) blocking internal-tool routes from IPs not in an allowlist. It's generic, not tool-specific: the allowed-ips config key is passed as a middleware parameter, e.g. RestrictToAllowedIps::class.':telescope.allowed_ips' and RestrictToAllowedIps::class.':pulse.allowed_ips'. Both tools' config middleware arrays reference this one class — don't fork a per-tool copy if a third internal tool needs the same protection; add another ':config.key' registration instead.

Allowlist entries may be a literal IP, a CIDR range (`10.0.0.0/8`, IPv6 too — matched with Symfony's `IpUtils::checkIp`) or a glob (`10.0.0.*`, matched with the `Str::is` fallback). CIDR support exists because .env.example documents CIDR for TRUSTED_PROXIES right above these keys, and a CIDR written here used to silently match nothing and lock everyone out.

The IP compared is `$request->ip()`, which reads X-Forwarded-For only for proxies TRUSTED_PROXIES names — see bootstrap.md. `tests/Feature/RestrictToAllowedIpsTest.php` pins that a forged X-Forwarded-For can't get past the list.
