---
paths:
  - 'app/Http/Resources/**'
  - app/Http/Resources/RowResource.php
  - 'app/Http/Controllers/Api/V2/**'
  - app/Models/Product.php
---

# Resource PHPDoc, relation loading and row occupancy

## Resource conventions: @property-read docblocks, whenLoaded, nested Resources
Every Resource documents the model's exposed shape with a `@property-read` PHPDoc block above the class instead of typed properties. Conditional/relation fields use `whenLoaded('relation', fn () => ...)`; nested resources are composed by instantiating another Resource inline (`new ProductResource($this->product)`), not by merging `->toArray()`. For a couple of fields, a plain inline array is fine instead of a full Resource class (see the pallet summary in CellResource). The id/name user summary is the exception that outgrew that: it is `UserSummaryResource`, shared by CellStatusLogResource (both `user` and a flag's `acknowledged_by`), CellVerificationReportResource and CellVerificationRoundResource — deliberately narrower than UserResource, which also exposes the email and admin flag. `JsonResource::withoutWrapping()` is set globally in AppServiceProvider — never add a `data` wrapper. Enums are always unwrapped (`->value`); Carbon dates use `->toDateString()` for date-only fields and `->toIso8601String()` for timestamps.

## RowResource.has_pallets must be supplied by the caller — there is no fallback
RowResource reads `has_pallets` straight off the model. Every caller supplies it: `Row::withHasPallets()` (a scope) when querying, or `Row::loadHasPallets()` for a model route-model binding already resolved. There is deliberately no fallback to `$row->hasPallets()`.

The resource used to fall back to that method when the attribute was absent, which read as a safe default but is a query every time (`cells()->whereHas('pallet')->exists()`), even when `cells` is already eager-loaded. On the unpaginated `GET /api/v1/rows/full` that meant one exists query per row — an N+1 that the fallback's existence hid, since every caller "worked correctly". Removing it trades that hidden cost for a visible contract, and outside production that contract is now enforced: `Model::shouldBeStrict()` is on (app-providers.md), so a caller that forgets the subquery throws `MissingAttributeException` rather than quietly reporting `has_pallets: false` — which on the rows index would have offered a delete button for a row that holds pallets. Production still degrades quietly, so the tests remain the real net: `Api\V1\RowController::index` and `full()` each assert no per-row exists query, and the admin show/edit tests assert the flag's actual value. Add the same kind of assertion alongside any new RowResource caller.

`Row::hasPallets()` still exists for `RowController::lockedRowHasPallets()`, which needs the live check inside a locking transaction rather than a value snapshotted at query time — do not route that through the resource's attribute.

## A list of 2+ models needs its relations eager-loaded, and cached models come back bare
Strict mode (app-providers.md) throws `LazyLoadingViolationException` only on a result set of two or more models, so a resource or price helper that reads a relation passes every single-record test and then fails on the first real list. Any endpoint that builds a collection of 2+ models must load every relation its resource reads:

- Products use `Product::WITH_STORE_LIST` (`Product::with(Product::WITH_STORE_LIST)`); extend that constant rather than adding a private list in a controller. A relation read for one record only (an offer's products) is `loadMissing()`-ed where it is read.
- Models cached through `remember_models()` come back from the cache **without relations** — the cache stores the bare models. Pass the relations as the last argument, `remember_models(Product::class, $key, $ttl, fn () => ..., Product::WITH_STORE_LIST)`, so a cache hit loads them as a miss does.
- Test with 2+ rows (ideally the same list from the cache and from the database), as `StoreApiStrictModeTest` does.
- The web storefront follows the same rule: a controller feeding a Blade page loads the relations the view reads (`with()` on the list, `loadMissing()` on one record), and a view that queries inline loads them in the same query. `StorefrontStrictModeTest` drives every parameterless `/temp` GET route as each caller and builds its data with `strictApiStore()` (`tests/Pest.php`); give a new page a row there instead of a test of its own.
- Strict mode also refuses a read of a column the table lacks. Don't read a column that is not in the schema (`carts.variant`, `Order::rep_serial`): production returns null silently, local and tests throw.

## Store API product lists and details go through `Product::storeOpenable()`
The store API controller's lists, `ProductController::show()` and `search()` all start from `Product::storeOpenable()` (published, approved, at least one `product_stocks` row). An imported catalogue row with no stock row prices at "0€" with stock 0, so it stays out of every list and its detail answers 404 JSON; extend the scope rather than repeating the `published`/`approved` pair. A product whose owner row is gone still opens, as an in-house product (`seller_id` 0), so the resource must not dereference `$data->user` unguarded. The best-seller cache key carries a version suffix: bump it when the list's rules change, because a cached list outlives a deploy by up to a day.

## Store API checkout reads: a representative's customer, a line's price, a seller's subtotal
- `StoreUser::current_customer` is the customer on the representative's cart lines, else the one of their open visit. `POST /representatives/pick-customer` only opens a visit, so the cart alone left `GET /user/shipping/address`, `POST /user/shipping/create`, `AddressPolicy` and `GET /representatives/current-customer` on the representative's own account until a product was added. The relation is cached on the model for the request; a test that reads it before and after picking a customer calls `app('auth')->forgetGuards()` between the requests.
- `cart_product_price()` prices a line with no variant at the product's `unit_price` when its stock row has none, as `CartController::add` does. Every total (`/cart-summary`, `/delivery-info`, `order/store`, coupons) goes through it, so a zero stock price cannot show as a 0€ cart.
- `/delivery-info` totals each seller's own lines; the minimum order, delivery product and fee are read only when `minimum_order_amount_check` is on, in `/cart-summary` and `order/store` alike.
- Carriers, pickup points and the minimum order are store settings and data (`carrier_range_prices` for the address's country zone or `free_shipping`, `pickup_point`, `minimum_order_amount_check`, `minimum_order_amount`, `default_specific_product`); cash on delivery needs `cash_payment` on and `cash_on_delivery` on every product in the cart. None of them is seeded: the filter seeders leave existing settings alone ([store-filter-seeding.md](../store-filter-seeding.md)).
