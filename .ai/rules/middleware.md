---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Keep the shared auth prop narrow
`HandleInertiaRequests::share()` sends `$request->user()?->only(['id', 'name', 'email'])`, NOT the model. Sharing `$request->user()` directly puts every current and future `users` column on every page of the app — that is how password hashes and tokens leak into HTML.

The TS counterpart is `AuthUser` in `resources/js/types/auth.ts` (consumed via `Auth` in `types/global.d.ts`). Widen both sides together or not at all. `tests/Feature/SharedAuthPropTest.php` asserts the prop has exactly 3 keys and is null for guests, so it fails loudly if the model is shared again.

Note there are two distinct user types on the frontend on purpose: `AuthUser` (types/auth.ts, the shared signed-in user) and `User` (types/admin.ts, the UserResource shape with `is_admin`). Do not merge them.
