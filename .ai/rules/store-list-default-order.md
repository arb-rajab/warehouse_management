---
paths:
  - 'app/Http/Controllers/Store/Admin/**'
  - 'app/Http/Requests/Store/Admin/Filter*Request.php'
  - 'app/Support/NewestFirst.php'
---

# Every store back office list defaults to newest first

## The rule
A store back office list shows records from newest to oldest unless the user picks
another sort: `created_at` descending, then the primary key descending. The key is
the tie-breaker that keeps pagination stable when rows share a timestamp (seeded or
imported rows often do). A table with no `created_at` is ordered by its key alone.
A sort the user picks (a `sort`/`type`/`rating` query parameter, a header click, a
"sort by" select) always comes first and wins.

## Use the helper, do not repeat orderBy lines
`App\Support\NewestFirst::apply()` is the one spelling. Make it the last ordering step
of the list query:

```php
$query->tap(NewestFirst::apply(...))->paginate($perPage)->withQueryString();
```

It only appends: after a user sort it supplies the tie-breakers (so `rating,desc`
still orders equal ratings newest first), and a column the query already orders by is
not added twice. Columns are qualified with the model's table, so a joined list (the
dynamic popups join `uploads`, the customers join a profit sub-query) never gets an
ambiguous `created_at`. Do not write `->orderBy('id')`, `->orderByDesc('id')` or
`->orderByDesc('created_at')->orderByDesc('id')` on a list query. Never order a list by
`status` first (countries, states and cities used to) or by a name (brands, blog
categories) unless it is a listed exception.

A relation query (`$user->notifications()`) goes through `->getQuery()` first. The
store tables all have `created_at` (see the migrations); the helper reads it from the
model, so a model that sets `$timestamps = false` while its table has the column must
be fixed, not worked around.

## Declare the default order of a new list
`tests/Feature/Store/StoreListDefaultOrderTest.php` fails when a controller under
`app/Http/Controllers/Store/Admin` paginates without calling `NewestFirst::apply(` and
without being named in its exception constants. Add the tests for a new list to
`tests/Feature/Store/Admin/ListDefaultOrderTest.php` (default is newest first, an
explicit sort overrides it, equal timestamps keep a stable order).

## Exceptions (with the reason)
Keep their order; do not "fix" them to newest first.

| Where | Order kept | Reason |
|---|---|---|
| `CategoryController::index` | `order_level` desc, id | the display priority staff maintain; the category tree reads by it |
| Typeahead endpoints behind `StoreSearchSelect` (every controller that `use`s `SearchesOptions`: `OrderEntryController::search`, `CampaignProductController` (products and brands), `CouponController::productOptions`, ...) | names starting with the term first, then name, then id (`SearchesOptions::orderByRelevance`) | pickers inside forms, not tables; see [store-search-select.md](store-search-select.md) |
| `ReportController` in-house sales | `num_of_sale` desc, then newest | a ranking by sales |
| `ReportController` searches | `count` desc, id desc | a ranking by search count |
| `CustomerController::pageViews` | view count desc | a ranking by views |
| `LanguageController` translation keys | id (catalogue order) | a dictionary imported in one go; reversing it is meaningless |
| `ConversationController::show` messages | chronological | a chat thread reads oldest to newest (its newest page opens first) |
| `SupportTicketController::show` replies | id desc | a reply thread, already newest first |
| `UploadController::files` | its `sort` parameter | has its own sort control; `newest` is the default |

Not tables, so not covered: `->get()` option lists for selects (alphabetical or by id),
`TripCheckpointController`/`TripMapController` checkpoint sequences (`sort_order_in_trip`),
and the dashboard's top-products card (ranked by sales).

The dated reading behind this list is
[store-list-order-audit-20261105](../store-list-order-audit-20261105.md).
