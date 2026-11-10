---
paths:
  - bootstrap/app.php
---

# Trusted proxies and security middleware order

## TRUSTED_PROXIES must be set in production or IP-based checks see the proxy, not the client
bootstrap/app.php reads TRUSTED_PROXIES (comma-separated IPs/CIDRs) and calls $middleware->trustProxies(at: ...) when non-empty. Skipping this in a production deploy behind a load balancer/CDN means every request's $request->ip() resolves to the proxy's IP, not the real client's. Two things downstream depend on the real IP being visible: the `login` RateLimiter (app-providers.md) — without it, every user shares one rate-limit bucket keyed on the proxy IP, so one attacker's failed logins can lock out everyone; and RestrictToAllowedIps (middleware.md) — without it, the Telescope/Pulse IP allowlist checks the proxy's IP instead of the real caller's, defeating the allowlist. Always set TRUSTED_PROXIES when deploying behind any reverse proxy/LB/CDN.

Never set `TRUSTED_PROXIES=*`. Trusting every proxy means trusting whatever the client puts in X-Forwarded-For, which hands an attacker control of both the Telescope/Pulse/health IP allowlist and the `login` RateLimiter's IP bucket — the exact two things this variable exists to make correct.

## Middleware append order: SecureHeaders wraps the WAF
`$middleware->append(SendSecureHeaders::class)` comes *before* `$middleware->append(BlockMaliciousRequests::class)`. `SendSecureHeaders` is the app's copy of bepsvpt's `SecureHeadersMiddleware`, plus a per-request CSP override (store-backoffice-migration.md §2.5). BlockMaliciousRequests aborts with a 403 from inside the pipeline, so a middleware appended after it never runs on a blocked request — with the order reversed, every WAF 403 shipped without any security headers. Covered by 'a request blocked by the WAF still carries the secure headers' in tests/Feature/SecureHeadersTest.php.

## A malformed encrypted id renders 404, and is still reported
The store puts `encrypt()`ed ids in URLs (`seller/orders/{id}`, conversations, tickets, coupons) and `decrypt()`s them in controllers, so a mistyped or tampered id throws `DecryptException`. `withExceptions()` renders it as a 404 through `$exceptions->render()`, not `$exceptions->map()`: `map()` swaps the exception before reporting, which would hide a real decryption fault (a rotated `APP_KEY`) from the logs, while `render()` keeps reporting the original. Controllers need no `try`/`catch` for this (the Store admin ones that have one keep their own handling). `tests/Feature/TamperedEncryptedIdTest.php` covers it.

## Gateway and Apple callbacks are exempt from CSRF in bootstrap/app.php, under `/temp`
Payment gateways (SSLCommerz, AamarPay, PayHere, Iyzico, bKash) and Sign in with Apple POST to the store from outside with no CSRF token, so `$middleware->preventRequestForgery(except: [...])` lists their paths. The store lives under `/temp`, so every pattern starts with `temp/`: the legacy list was written for the unprefixed app and sat in an unregistered `VerifyCsrfToken` class, so these POSTs all answered 419 and a customer who had paid at the gateway never had the order completed. An exempt callback must verify itself with the provider (`GatewayPaymentService`, the gateway's signature or API), never trust the request. A new gateway or callback that POSTs needs its path added here and a row in `tests/Feature/GatewayCallbackCsrfTest.php`, which also pins the exact list so it is not widened by accident. Laravel skips the CSRF check when `APP_ENV=testing`, so that test runs as `local`.
