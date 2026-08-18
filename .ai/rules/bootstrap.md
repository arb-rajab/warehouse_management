---
paths:
  - bootstrap/app.php
---

# Bootstrap

## TRUSTED_PROXIES must be set in production or IP-based checks see the proxy, not the client
bootstrap/app.php reads TRUSTED_PROXIES (comma-separated IPs/CIDRs) and calls $middleware->trustProxies(at: ...) when non-empty. Skipping this in a production deploy behind a load balancer/CDN means every request's $request->ip() resolves to the proxy's IP, not the real client's. Two things downstream depend on the real IP being visible: the `login` RateLimiter (app-providers.md) — without it, every user shares one rate-limit bucket keyed on the proxy IP, so one attacker's failed logins can lock out everyone; and RestrictToAllowedIps (middleware.md) — without it, the Telescope/Pulse IP allowlist checks the proxy's IP instead of the real caller's, defeating the allowlist. Always set TRUSTED_PROXIES when deploying behind any reverse proxy/LB/CDN.
