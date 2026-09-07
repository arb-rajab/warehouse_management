---
paths:
  - bootstrap/app.php
---

# Bootstrap

## TRUSTED_PROXIES must be set in production or IP-based checks see the proxy, not the client
bootstrap/app.php reads TRUSTED_PROXIES (comma-separated IPs/CIDRs) and calls $middleware->trustProxies(at: ...) when non-empty. Skipping this in a production deploy behind a load balancer/CDN means every request's $request->ip() resolves to the proxy's IP, not the real client's. Two things downstream depend on the real IP being visible: the `login` RateLimiter (app-providers.md) — without it, every user shares one rate-limit bucket keyed on the proxy IP, so one attacker's failed logins can lock out everyone; and RestrictToAllowedIps (middleware.md) — without it, the Telescope/Pulse IP allowlist checks the proxy's IP instead of the real caller's, defeating the allowlist. Always set TRUSTED_PROXIES when deploying behind any reverse proxy/LB/CDN.

Never set `TRUSTED_PROXIES=*`. Trusting every proxy means trusting whatever the client puts in X-Forwarded-For, which hands an attacker control of both the Telescope/Pulse/health IP allowlist and the `login` RateLimiter's IP bucket — the exact two things this variable exists to make correct.

## Middleware append order: SecureHeaders wraps the WAF
`$middleware->append(SecureHeadersMiddleware::class)` comes *before* `$middleware->append(BlockMaliciousRequests::class)`. BlockMaliciousRequests aborts with a 403 from inside the pipeline, so a middleware appended after it never runs on a blocked request — with the order reversed, every WAF 403 shipped without any security headers. Covered by 'a request blocked by the WAF still carries the secure headers' in tests/Feature/SecureHeadersTest.php.
