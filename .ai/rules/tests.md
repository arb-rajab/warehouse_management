---
paths:
  - 'tests/**'
---

# Tests

## Authenticate API tests with actingAsMobileUser()
Use the `actingAsMobileUser()` helper in `tests/Pest.php` instead of writing `Sanctum::actingAs(User::factory()->mobileUser()->create(), ['*'])`. It returns the user, so tests that assert on who performed an action can do `$user = actingAsMobileUser();`. There should be zero `Sanctum::` references outside Pest.php.

## Every Inertia-rendering route needs a "can be rendered" test
For every controller action that returns `Inertia::render(...)` (index/show/create/edit pages), add a dedicated test asserting the route returns 200 and renders the expected component — e.g.:

```php
test('the X screen can be rendered', function () {
    $response = $this->get('/path');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Expected/Component')
    );
});
```

Don't rely on a data-assertion test (e.g. "user can view the list") to implicitly cover this — those tests check payload shape, not that the bare render path works for every access level that can reach it (guest vs authenticated, where relevant). `LoginControllerTest` was missing this for `GET /login` until it was added.

## Assert every property a response returns, not just the one under test
A response-shape test must pin down the *whole* payload for at least one record, not a couple of interesting fields. A partial assertion silently passes when a resource stops returning a key the UI or mobile app reads.

Inertia: scope the record with a closure — `->has('transfers.data.0', fn (Assert $t) => $t->where(...)->has('pallet', fn (Assert $p) => ...))`. Nested `Assert` scopes fail if any key inside them is left untouched, so the closure both asserts values and proves no key was added or dropped. Root-level props are *not* checked this way, so always scope down to the record.

JSON APIs: `expect($response->json())->toEqual([...])` (or `firstWhere('id', ...)` on a collection) with the full expected array. Prefer this over `assertJsonStructure()`, which passes when extra keys leak in.

Seed the values explicitly (`Product::factory()->create(['name' => 'Widgets', 'image_url' => '...'])`) so the assertion checks real values rather than echoing the model back. Cover conditional keys in both directions too — a `whenLoaded`/`when` key needs a case where it is present and a case where it is absent or null.

## Every admin route needs a mobile-user rejection test, colocated with the route
Admin panel routes live behind `role:admin`. Every one of them — every action, not just the happy path — needs a test that a mobile app user (`User::factory()->mobileUser()->create()`) gets a 403, alongside the existing unauthenticated-caller test. Both are required; neither substitutes for the other.

These tests live in the controller's own test file (`RowControllerTest`, `UserControllerTest`, `CellStatusLogControllerTest`, …), next to the happy path for that action — not in a separate central access-test file. The old `MobileUserAccessTest` was dissolved into the controller test files for this reason; don't reintroduce it. Routes with no controller (e.g. the `/admin` redirect) go in `RootRedirectTest`.

Mutating actions (POST/PUT/DELETE) additionally need an explicit "nothing changed" DB assertion — a count, or a `fresh()` check on the exact field the request tried to change. Don't collapse these into a dataset; the assertion differs per route.

Cross-check with `php artisan route:list --except-vendor` when adding an admin route, so no action ships without its rejection test.

## Every edit/update/destroy action needs a "non-existent id returns 404" test
For every controller action that resolves a model via route parameter (edit/update/destroy — anything typed as an Eloquent model in the method signature, e.g. `Row $row`, `User $user`), add a test that an authenticated, authorized caller hitting that route with an id/key that doesn't exist gets a 404 (`$response->assertNotFound()`).

This relies on Laravel's implicit route model binding already 404ing on a missing model — the test just pins that behavior down so it doesn't silently break (e.g. if a route were changed to manual lookup with a fallback, or binding were scoped oddly).

Colocate these tests with the other tests for that action (same file, near the other edit/update/destroy tests), following the same colocation convention as the mobile-user-rejection and unauthenticated-caller tests. Use an id/key that is well-formed but does not exist (e.g. a large integer for numeric ids, an unused letter for `Row`'s `letter` route key) — not a malformed value, since that's a different (validation) concern.

Example (Row uses a `letter` route key, User uses default `id`):
test('viewing the edit page for a non-existent row returns a 404', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/admin/rows/ZZ/edit');
    $response->assertNotFound();
});

## Backdate model timestamps with backdate(), build transfer pairs with createTransferPair()
`created_at` is never mass-assignable, so a factory's `create(['created_at' => ...])` silently ignores it — use the `backdate($model, $timestamp)` helper in `tests/Pest.php` (`forceFill(['created_at' => ...])->save()`, returns the model) instead of fighting mass assignment or freezing `Carbon::now()` for the whole test.

`createTransferPair($pallet, $source, $destination, $outAt, $inAt)` (also in Pest.php) creates the matching `transferred_out`/`transferred_in` `CellStatusLog` row pair a real transfer writes in one transaction (see concerns-models.md, cell-status-logs.md), already backdated — use it instead of hand-rolling both factory calls whenever a test needs a transfer pair or a next-log/duration chain to test against.

## Grep for ALL occurrences of a pattern before fixing any single one
Before fixing (or updating) one instance of a pattern — a selector, a key name, a PHPDoc annotation — grep the entire relevant file (or codebase) for all occurrences of that pattern and fix every one in the same change:

```bash
grep -n "get('input')" resources/js/pages/Admin/Products/Index.test.ts
```

Never fix the first hit and push without enumerating the rest. A partial fix looks green locally but fails in CI when a second occurrence triggers the same failure, costing a full CI round trip per missed occurrence. Enumerate first, fix all, then push once.

## Authenticate admin-panel tests with actingAsAdmin()
Use the `actingAsAdmin()` helper in `tests/Pest.php` instead of writing `$this->actingAs(User::factory()->create())` — it mirrors `actingAsMobileUser()` and returns the user for tests that need to reference it (its own id/name/etc., e.g. self-delete or self-demote tests). Only write the raw factory+actingAs pattern when the acting user needs non-default factory attributes (e.g. a specific name/email for an assertion).

## Don't hardcode a unique column's value beside a factory that generates its own
`RowFactory` draws `letter` from `fake()->unique()->lexify(...)`, and faker's `unique()` only tracks what faker itself produced — it knows nothing about a letter written literally in a test. So a test that hardcodes `['letter' => 'Z']` *and* calls a factory that creates a row of its own can collide on `rows.letter`, as an intermittent `UniqueConstraintViolationException` that passes on one run and fails on the next.

The factories that quietly create a row: `Pallet::factory()` and `CellVerificationReport::factory()` when given no `cell_id`, and `Cell::factory()` with no `row_id` (see `CellFactory`'s `Row::withoutEvents(...)`).

Pick one convention per test: either hardcode the letters *and* give every factory an explicit `cell_id`/`row_id` so none of them creates a row, or let the factories draw every letter. Only hardcode a letter when an assertion actually reads it — `expect($response->json('rows'))` needs 'A', a row that exists purely as noise does not.

## When changing a shared threshold constant, grep for the literal values it produces — not just its symbol name
Changing a day/month-count constant (e.g. `BuildsDashboardStats::EXPIRING_WINDOW_DAYS` → `EXPIRING_WINDOW_MONTHS`) only surfaces call sites that reference the constant or its wrapping method by name. Other tests can depend on the *old boundary it produced* purely through a hardcoded date/day literal and a comment ("outside the 7-day window") — with no symbol to grep for. `PalletObserverTest` hardcoded `expiration_date => '2026-09-12'` as "outside the 7-day window (until 2026-08-20)"; moving to 1/2/4/6-month windows made `2026-09-12` fall *inside* the new 1-month window (until `2026-09-13`), flipping two assertions from `0` to `1` — caught only in CI, not by grepping `EXPIRING_WINDOW_DAYS`/`expiringWindow()`. Before finishing a threshold-boundary change, also grep the affected date range's literal dates/day-counts (not just the constant/method name) across `tests/` to find fixtures that silently assumed the old boundary.
