# Backend replies (2026-11-04)

Replies to **BR-20261104-A**. Every point is quoted by its number. Paths are under `/api/v1/store`.
"Done" means changed and covered by `tests/Feature/StoreApiBackendRequestsTest.php`
(A.4 also by `tests/Feature/Models/OrderRelationsTest.php` and `tests/Feature/Store/Admin/ProductControllerTest.php`).

**On dev since 2026-11-05 08:14 UTC** (`develop` at `d9e05a5e`). The first deploy of these changes, at 07:46,
stopped before going live: Symfony security advisories published on 2026-11-04 failed the server's dependency
audit, so dev kept the 2026-11-04 release. The Symfony update (#545) cleared it. If you tested between 07:46
and 08:14, you were still on the old release. Staging and production do not have these changes yet; they
arrive with the next promotion, which has to include #545 or it stops at the same audit.

**I did not re-run your steps on dev**, because I cannot reach it. A.1, A.3 and A.4 were reproduced in tests from the
steps you gave. Please re-test them; the orders and products you named are listed under each point.

| Point | State |
| :-: | :--- |
| A.1 | Done: the 500 is fixed, the fee is charged, and the summary's total includes it. Below the minimum with no fee product: refused with 422 |
| A.2 | Done: one VAT percent everywhere. Catalogue prices: see the answer |
| A.3 | Done: 422 with `errors.variant` |
| A.4 | Done: order lines resolve by their stored product id; "duplicate" no longer copies the serial or the SKU |
| A.5 | `cart-summary` done; `admin_verified` answered, not changed |
| A.6 | Answered, not changed: the owner has to decide |
| FYI | Cause explained; the check to run on staging and production is below |

---

## BR-20261104-A

**A.1 Minimum order with a fee product.** Your steps reproduce the 500 for a customer and for a rep. The cause:
the fee line was built by copying another cart line with all its columns (`id`, `created_at`, …), and this app
refuses that copy. Two more defects sat behind it. The fee line was never in the order even when it was
created, because the order had already read the cart. And a refused order left the fee line in the cart.
The web checkout had the same code, so it is fixed too.

What happens below the minimum now (the rule BR-20261103-B.5 described):

- The cart's lines before VAT (`sub_total`; a rep's own price where they changed it) are compared with
  `minimum_order_amount`. Your 32.70 is 30.00 before VAT. Lines of the fee product itself do not count.
- Below it, the order gets one line of the fee product, with its own VAT, in the same transaction as the order.
  A refused order (stock, coupon, wallet) takes the fee line back, and the cart stays as it was.
- The fee line is placed even when the fee product is unpublished, and it reserves no stock, so a fee product
  can stay hidden from the catalogue. Use a product that exists only for the fee: a real product set as the fee
  (like 310597) does not count toward the minimum and takes no stock while it is the fee product.
- **Below the minimum with no fee product to add** (the setting is empty or names a deleted product, as for
  your order 1837), the order is now **refused**:
  `422 {"combined_order_id":0,"result":false,"message":"The minimum order amount is €50.00. ...","errors":{"minimum_order_amount":["..."]}}`.
  Before, it was accepted with no fee, so the minimum was not enforced. The back office cannot save the check
  without a fee product, so this case comes only from older or deleted settings.

You asked whether to refuse or to add the fee. Adding the fee is the behaviour that was already in place and
documented, so I kept it. If the owner wants small orders refused even when a fee product is set, that is a
small change.

**`grand_total` and `delivery_cost`.** The order is right, and the summary was wrong. `GET /cart-summary` now
prices the fee the way the order does. `delivery_cost` is the fee before VAT, `tax` includes the fee's VAT,
and `grand_total` / `grand_total_value` include both. With your cart (3 × 10.00 at 9 %, a 5.00 fee at 21 %):
`sub_total` €30.00, `tax` €3.75, `delivery_cost` €5.00, `grand_total` €38.75, and the order's `grand_total`
is 38.75. `GET /delivery-info` is unchanged: it still reports the fee product's unit price per seller.

**A.2 VAT percent.** The cause: a product's VAT can come from two places. One is the Otajer feed
(`products.tax`, 9 or 21). The other is the back office VAT section, which writes tax rows. The VAT money
already read the tax rows, but `tax_percent`, the product's `tax` and the stored order line read only
`products.tax`, which is 0 for your product. There is now one rule: **a product with tax rows uses them,
otherwise `products.tax`**. Done:

- `POST /carts`: `tax_percent` is 9 for 316703. `tax_amount` is unchanged (2.70). The legacy `tax` field is
  unchanged for the old app.
- `GET /products/{id}` and the product lists: `tax` is the percent (9), `tax_type` is `"percent"`, and the new
  field `price_includes_tax` says how to read the prices beside it (below).
- The order line stores the percent in `order_details.tax` (9), from the API, the web checkout, offers and the
  back office order entry. `GET /purchase-history-items/{id}` shows it in `tax_percent`. Lines stored before
  this change keep 0: order 1840, line 1842 stays as it is. Its `tax` money (2.70) was already right.

**Are prices VAT-inclusive?**

| Where | Price | VAT |
| :--- | :--- | :--- |
| Cart (`price`, `sub_total`), order lines, `subtotal` in the history | before VAT | added: `tax_amount`, `tax`, inside `grand_total` |
| Catalogue (`main_price`, `stroked_price`, …), product **with** back office tax rows | **includes** VAT (`price_includes_tax: true`) | `tax` = the percent |
| Catalogue, product taxed only by the Otajer feed | before VAT (`price_includes_tax: false`) | `tax` = the percent |

That is why 316703 showed 10.90 in the catalogue and 10.00 + 0.90 in the cart. The catalogue prices come from
the storefront's legacy price code, which adds the tax rows and nothing else. Making every catalogue price
before VAT would change prices the storefront shows, so I left that for the owner (below). Until then, read
`price_includes_tax`.

**A.3 Unknown variant.** Done. `POST /carts/add` with a variant the product has no stock row for (or a
non-text `variant`) answers `422 {"result":false,"message":"This product has no such variant.","errors":{"variant":["..."]}}`
and adds nothing. The answer is the same for every account. A rep without an open visit still gets the earlier
403.

**A.4 An order line changing product.** You were right. Order lines found their product by the Otajer serial
(`order_details.product_serial` = `products.serial`), not by the id stored on the line. "Duplicate" copies the
serial, so the newest copy won. Done:

- Order lines resolve by `order_details.product_id`. That id is the Otajer `Mat_ID` the product sync keys on,
  so it does not change. On dev, orders 1838 and 1841 should show "VAT Test Chickpeas 400 g" again.
  Nothing in the database needed to change.
- "Duplicate" (back office, seller panel and seller API) no longer copies the product's `serial` or its stock
  rows' `sku`: the copy gets none. Copies made before this change keep the source's serial. With the first fix
  that no longer affects order lines, but you can clear it on 316704 and 316705 in the product form.

**A.5 Shapes.**

- `GET /cart-summary` with an empty cart now has the same types as a filled one: `delivery_cost` is
  `"€0.00"` and `coupon_code` is `null`. `grand_total_value` is a JSON number either way. A whole amount is
  written without decimals (`0`, `30`) in both cases, so parse it as a number, not specifically a double.
- `admin_verified`: **not changed.** The account object (`sign-in`, `auth/info`, `auth/user`) has sent `1`/`0`
  since before the new app, and the old app reads it. `data` in `pick-customer` is the warehouse customer
  resource, which has always sent a boolean, and the warehouse API shares it. Changing either could break the
  app that reads it. Treat both as truthy: `1`/`true` is verified. If you want one type, I can add a boolean
  field beside the old one.

**A.6 A rep ordering for an unverified customer.** Today this is allowed, and not by a decision made for it.
The 403 an unverified customer gets comes from the price rule (BR-20261103-B.6: they may not pay what they
cannot see). The rule checks the account that places the order, and a rep sees prices. Blocking it is a one-line
change, but the owner has to decide. Customers a rep creates in the app (`create-customer`) start unverified, so
blocking would also stop a rep ordering for a new customer until the back office verifies them. My
recommendation: allow it, because the rep is accountable for the order, unless verification means more than
"may see prices".

**FYI, the currency.** The cause matches what you saw. Money is formatted from the settings
`no_of_decimals` and `symbol_format`. When those rows are missing, it uses 0 decimals and the symbol after the
number ("11€"). Saving the Currency page wrote them. I cannot reach staging or production. Run this on each:

```sql
SELECT type, value FROM business_settings
WHERE type IN ('no_of_decimals', 'symbol_format', 'decimal_separator', 'system_default_currency');
```

If a row is missing or its value is blank, save Back office, Setup, Currency once there, as you did on dev.

Since 2026-11-05 this can no longer happen. A missing or blank `no_of_decimals` or `symbol_format` now means
2 decimals with the symbol first, the same values the Currency page shows (`BusinessSetting::CURRENCY_DEFAULTS`).
Saving the page is still worth doing, so the stored values are explicit.

---

## Open from earlier requests

Nothing new since 2026-11-03, except the stars sort, which I found already exists. See
[backend-replies-20261103.md](backend-replies-20261103.md):

- **BR-20261103-D.3.** I have no record of a D.3 in that reply. On the idempotency key (BR-20261103-B.2): a
  successful answer is remembered for **24 hours** per account and key. Dev runs it. Whether the old server has
  it depends on what is deployed there, which I cannot see. You can check on any server: a repeat with the
  same key answers with the header `Idempotent-Replayed: true`.
- **BR-20261102-D (Firebase, test push).** Still waiting on the server: run `php artisan store:send-test-push
  <token from com.newstore.app.dev>` on dev. It prints the Firebase project it uses and whether the token was
  accepted (D.1 and D.4).
- **BR-20261102-B.4 / BR-20261103-D.6 (reset code on dev).** Unchanged: no fixed code. Set `MAIL_MAILER=log` on
  dev and the code is in `storage/logs/laravel.log`, or give me an inbox you can read.
- **Sort by stars.** It exists: `GET /products/search?sort_key=top_rated` sorts by `rating`, highest first. The
  other keys are `price_low_to_high`, `price_high_to_low`, `new_arrival` and `popularity`, and the default is
  name. If you meant a different list, tell me which one.

## For the owner

1. **Catalogue prices and VAT (A.2).** Products with back office tax rows show catalogue prices with VAT;
   products taxed by Otajer show them without. Should every catalogue price be before VAT, to match "VAT on
   top" (this changes the storefront's displayed prices for products with tax rows)?
2. **Below the minimum (A.1).** Keep adding the fee, or refuse small orders?
3. **Reps and unverified customers (A.6).** Allow, as now, or refuse?
