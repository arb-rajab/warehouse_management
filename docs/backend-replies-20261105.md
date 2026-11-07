# Backend replies (2026-11-05)

Replies to **BR-20261105-A**. Every point is quoted by its number. Paths are under `/api/v1/store`.
"Done" means changed and covered by tests in `tests/Feature/StoreApiBackendRequestsTest.php`
(A.2 and A.3 also by `tests/Feature/Models/ProductTest.php` and `BrandTest.php`).

**Not deployed anywhere yet.** These changes are on a branch that has to be merged into `develop`, and later
promoted to `staging` and `main`. **I could not run the PHP tests in the environment I wrote this in** (it has PHP
8.3 and the app needs 8.5, and Pest cannot be installed there). The code and tests were checked by reading and by
`php -l`; CI is the first real run. Please re-test on dev after the deploy, as you did for BR-20261104-A.

Every change is additive: a new field, a new optional parameter, or a new header. Nothing the current app versions
read was changed or removed. The one exception to "nothing changes" is in A.3: a brand row has a new field, so a test
that compares a whole brand row exactly will see it.

| Point | State |
| :-: | :--- |
| A.1 | Answered from git and the deploy history: **production (`main`) does not have it**; `develop` and `staging` do |
| A.2 | Done: `sort_key=stars_high_to_low` and `min_stars` on search, category and brand lists |
| A.3 | Done as option (a): `category_ids` on every brand row |
| A.4 | Answered: none of the three exists in the API and nothing is planned that I can see; the owner decides |
| A.5 | a, c, d done. b: one waste removed, not measured. e: not changed, causes listed |

---

## BR-20261105-A

**A.1 Idempotency-Key on the old server.** I cannot call any server from here, so this comes from git and the
deploy workflow's history, not from a request. The key was added in `5f45f20` (2026-11-03, PR #537).

| Branch (server) | Has `5f45f20` | Last deploy |
| :--- | :-: | :--- |
| `develop` (dev) | yes | 2026-11-07 03:58 UTC, success |
| `staging` | yes | 2026-11-07 05:03 UTC, success |
| `main` (production) | **no** | 2026-10-27 08:27 UTC, success (PR #508) |

If your "old server" is production, **it does not have the fix, so keep "send again" off there.** Production also
lacks everything from BR-20261103 and BR-20261104 (the fee line, the VAT percent, the 422 for a variant). The owner
(whoever merges `staging` into `main`) is the one who can change that, and who can confirm what runs on a server:
the `deploy` workflow run for a branch names the commit it deployed.

A harmless call to check a server yourself, because an `Idempotent-Replayed: true` header cannot be shown without
placing an order twice: send `POST /order/store` with an **`Idempotency-Key` of 129 characters**, from an account
whose cart is **empty** (or any account on dev or staging).

- A server with the fix answers `422` with `errors.idempotency_key`, before it reads the cart.
- A server without it ignores the header. With an empty cart it answers that the cart is empty and places nothing.
  **With a filled cart it would place the order.** So use an empty cart for this probe, never a real customer's.

**A.2 Sort by stars.** Done. `stars` is the `amount` of the product's collection; a product with no collection is 0.

- `sort_key=stars_high_to_low`: highest first, ties by name (then id, so a page boundary is stable). On
  `GET /products/search`, `GET /products/category/{id}` (and `sub-category`, `sub-sub-category`, which are the same
  action) and `GET /products/brand/{id}`.
- `min_stars=4`: only products graded at least 4, on the same three routes, alone or with the sort. `0` or less keeps
  every product. A value that is not a number is ignored, like `min` and `max` in the search.
- `sort_key=top_rated` is unchanged: it still sorts by the customers' `rating`.
- Without `sort_key`: the search still sorts by name. A category list already put the best-graded first, then the
  newest; that stays. The brand list stays newest first.
- The `stars` field is unchanged and visible to everyone, as it is now.

**A.3 A brand's categories.** Done as (a). Every row of `GET /brands` (and `GET /brands/top` and
`GET /filter/brands`, which share the row) has `category_ids`, ascending, for example `[2, 5, 9]`; a brand with
nothing visible has `[]`. It is one query for the whole page, not one per brand. I did not build (b).

What counts is what the product list shows: published, approved, at least one stock row, physical, and from a
seller the shop shows. The id is the product's **own** category, which is exactly what
`GET /products/search?categories={id}&brands={brandId}` matches, so every id listed has at least one result there
(a test checks that). It adds no parents and no children. `GET /categories` lists top-level categories, so a brand
whose products sit in a sub-category gets that sub-category's id here, and the app has to look it up by id or use
`GET /sub-categories/{id}`. If you would rather get the top-level parent as well, tell me.

**A.4 Contracts that do not exist yet.** Not in the API today, and I cannot say it is planned, because that is the
owner's decision and nothing in the repository or the earlier replies records one. So: **do not build these screens
yet.** What exists: the web shop has all three (cancel: `purchase_history/destroy/{id}`, allowed only while the order
is pending and unpaid, and it puts the stock back; a notifications page; support tickets with list, create, reply),
so the rules are known and an API for each would be small. If the owner says yes, I would propose, as a starting
point only: `POST /order/{id}/cancel` (no body, 422 when the order is no longer pending and unpaid),
`GET /notifications?page=` and `POST /notifications/read`, `GET /support-tickets`, `POST /support-tickets`
(`subject`, `message`) and `POST /support-tickets/{id}/reply` (`message`). No dates until it is decided.

**A.5 Smaller performance points.**

a) **Cache validators.** Done. These now answer with an `ETag`, and `If-None-Match` gets an empty `304`:
`/categories`, `/categories/featured`, `/categories/home`, `/categories/top`, `/sub-categories/{id}`, `/brands`,
`/brands/top`, `/sliders`, `/banners-one`, `/banners-two`, `/banners-three`, `/payment-types`. (`languages`,
`banners`, `colors`, `currencies` and `business-settings` already had it.) The ETag is made from the answer, so a
changed list gets a new one and is never answered with a stale 304; `/payment-types` depends on the cart, and a
changed cart changes the answer and the ETag. No freshness time is set, so a client still has to ask. Be aware that
the server still builds the answer to compute the ETag: this saves bytes on the phone, not time on the server.

b) **`/products/search` speed.** Partly, and **not measured**: I have no access to dev's data. One waste removed:
the search applied "published, approved, has a stock row" twice, which ran the stock-row test twice for the same
result. The rest cannot be made the same path as `GET /products/brand/{id}`, because the search matches the
category exactly and the category route also includes its sub-categories, so the answers differ. If you want the
fast route for ids only, `GET /products/category/{id}?brands={brandId}` already exists, takes the same row shape,
includes sub-categories, orders by grade then newest, and now takes `per_page`. Please measure the search again.
If it is still slow, send me the two slowest URLs and I will read the query plan on real data before adding an
index.

c) **Page size.** Done. `per_page` (1 to 50) on `GET /products`, `/products/search`, `/products/category/{id}` (and
its two siblings), `/products/brand/{id}`, `/products/inhouse`, `/products/new_products`, `/products/featured`,
`/products/digital`, `/products/seller/{id}` and `GET /brands`. Without it, or with a value that is not a whole
number of at least 1, the answer is 10 rows as before. **A value over 50 is lowered to 50 (it is not a 422, unlike
`representatives/get-customers`)**; read `meta.per_page` for what you got. Not covered: the cached short lists
(`todays-deal`, `best-seller`, `related`), the shop product lists, and `/categories`, which is not paged.

d) **One call after a cart change.** Done, as an opt-in parameter so the old app pays nothing: add
`with_cart=1` to `POST /carts/add`, `POST /carts/change-quantity`, `DELETE /carts/{id}` or
`POST /cart/update_item_price`. A **successful** change then also answers `cart` (exactly what `POST /carts`
answers) and `summary` (exactly what `GET /cart-summary` answers), beside the usual `result` and `message`. A refusal,
a 404 or a call without the parameter is unchanged. An account that may not see prices gets the same blanked prices
as on those two routes (`price_hidden: true` in `summary`, and the header `X-Prices-Hidden: 1`).

e) **Thumbnails.** Not changed; I cannot reproduce it, and BR-20261103-C.2 is **not on production** (A.1), which is
the first thing to rule out. Otherwise, a list row keeps the original picture in `thumbnail_image` when its upload
is an external link (the Otajer pictures stay one until `fetch:otajer-photos` has downloaded them), is stored on S3,
is a file that is not a raster image, or is no wider than 400 px and no bigger than 150 KB. A copy is made when the
URL is first asked for. `photos[].path` is always the original, so a list row that reads `photos[0].path` shows the
full-size picture by design. A real thumbnail's URL contains `/uploads/thumbs/`. If one of your rows has a URL
without it on staging, send me that product's id and I will say which of the cases it is.
