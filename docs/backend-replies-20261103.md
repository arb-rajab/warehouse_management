# Backend replies (2026-11-03)

Replies to the six open requests of the new app's developers. Every point is quoted by its ID and number.
Paths are under `/api/v1/store`. "Done" means changed in this branch and covered by
`tests/Feature/StoreApiBackendRequestsTest.php`.

**Nothing here was checked against dev or staging.** This branch has to be deployed before any "done" shows
up there. Several findings in the requests (a new token on every `POST /auth/info`, the raw user model, 404 on
the policy pages) describe what dev and staging run now, which can be older than this code; where the code
here already behaves differently, the reply says so.

| Order | ID | State |
| :-: | :--- | :--- |
| 1 | BR-20261103-A | A.1 and A.2 done; A.3 has a command to run on dev |
| 2 | BR-20261103-B | B.1 to B.4, B.6 done; B.5 answered; B.7 done as an opt-in header |
| 3 | BR-20261102-B | B.1 to B.3 done; B.4 declined with an alternative; B.5 answered |
| 4 | BR-20261102-D | D.2 to D.5 done; D.1 needs one fact from the server |
| 5 | BR-20261101-A | Point 1 done; point 2 answered, not built |
| 6 | BR-20261103-C | C.1 and C.2 done |

---

## BR-20261103-A

**A.1 Who owns order 1834.** The customer is the account the rep picked; the rep placed it. Fields:

| Meaning | Order list (`GET /purchase-history`) | Details (`GET /purchase-history-details/{id}`) |
| :--- | :--- | :--- |
| Customer (account) | `customer_id`; `name` and `otajer_id` are the customer's | `customer_id`, `customer_name` |
| Placed by | `placed_by_id`, `placed_by_name` | `placed_by_id`, `placed_by_name` |
| Placed by a rep? | `placed_by_rep` (bool) | `placed_by_rep` (bool) |
| Unchanged | `user_id` = who placed it (the rep for a rep's order) | `user_id` = who placed it |

For an ordinary order `customer_id` and `placed_by_id` are the same account and `placed_by_rep` is false.
Yes, the order shows in customer 354's own history: the list returns the orders the account placed and
the orders placed for it (`for_customer`). The details call is allowed for both accounts by `OrderPolicy::view`.

Two defects behind what you saw are fixed:

- `shipping_address` carried the rep's `name` and `email` beside the customer's street and phone. It now
  carries the customer's name and email. Orders already stored keep what they hold.
- The list's `payment_status` filter was attached with `OR` to the "placed by / placed for" test, so a
  filter could return other people's rows. The two tests are grouped now.

Not changed: orders stored before this change keep their old `shipping_address`.

**A.2 Delivery date.** Done, for `POST /order/store` and for `payments/pay/cod|manual|wallet`, which place
orders the same way. A blank, missing, unreadable, past, too-near or weekend date answers **422**:

```json
{"combined_order_id":0,"result":false,"message":"The earliest delivery date is 2026-11-05.",
 "errors":{"delivery_date":["The earliest delivery date is 2026-11-05."]}}
```

Rules, all in `config/store.php` (`delivery`): the date is required; sent as `YYYY-MM-DD` (`DD-MM-YYYY` is also
read, and the order stores `YYYY-MM-DD`); not earlier than **today + 2 days**, so today and tomorrow are
skipped; **Monday to Friday** only; "today" is read in `Europe/Amsterdam`, not the server's UTC. The
three values are `STORE_DELIVERY_TIMEZONE`, `STORE_DELIVERY_LEAD_DAYS` and the `weekdays` list. The messages
are in English and Arabic.

Older rows that hold `1970-01-01` are not rewritten. The details call now answers `delivery_date: null` for them
instead of `01-01-1970`.

**A.3 Carrier and minimum order on dev.** The cause is data: `delivery-info` builds `carriers` from the active
carriers that have a price range for the zone of the cart address's country (or free shipping), and the minimum
order appears only when the setting `minimum_order_amount_check` is 1. I cannot reach dev, so there is now a
command to run there (it refuses production; use the back office, Setup, there):

```bash
php artisan store:setup-delivery --carrier="Test carrier" --price=6 --transit-days=2 \
    --minimum=50 --fee-mat-id=<mat_id of the delivery fee product>
```

It puts the country (default Netherlands) in a zone if it has none, creates the carrier with a price for that
zone, and sets the minimum order amount and the fee product. Running it again changes the price and adds nothing
twice. After it, `GET /delivery-info` for a customer whose cart address is in the Netherlands lists the carrier and
returns `minimum_order_amount` and `default_delivery_fees`. `--fee-mat-id` has to be a product that exists on
dev. Without `--minimum` the order settings are not touched. The carrier's `transit_price` is 0 unless the shipping
type is "carrier wise shipping" (back office, Setup, Shipping); the command warns when it is not.

---

## BR-20261103-B

**B.1 Leaked user fields.** `GET /auth/user` now answers the same `user` object as sign-in and `POST /auth/info`
(built field by field, so a column added later is not sent): `id, type, name, otajer_id, company_name, email,
avatar, avatar_original, phone, is_rep, rep_has_package, rep_discount_percentage, company_address,
shipping_address, tax_number, country, trade_license, bank_account_number, email_verified, admin_verified`.
`verification_code`, `provider_id` and `refresh_token` are not in it. It is a read: no token is created.
Whether staging is fixed depends on its deploy; check `GET /auth/user` there after the next deploy. `POST
/auth/info` and `POST /get-user-by-access_token` already return only fields chosen one by one.

**B.2 Order idempotency.** Done. Send `Idempotency-Key: <up to 128 characters>` on `POST /order/store` (and
`payments/pay/*`). Same account plus same key:

- the first **successful** call places the order and its answer is kept for 24 hours;
- a repeat returns that same answer with the header `Idempotent-Replayed: true` and places nothing;
- a refused call (empty cart, stock, delivery date) is not kept, so the same key can be used again;
- two calls with the same key at once: the second waits for the first, then gets its answer; if the first is
  stuck for 15 seconds the second gets 409 `result:false`;
- a key longer than 128 characters is 422 (`errors.idempotency_key`).

Use one new key per attempt to place a given cart and reuse it for retries of that attempt. Without a key
nothing guards against a duplicate, so your fallback (block a second send, send the user to the order history) is
still right for a client that did not send one. I did not build a "was an order placed in the last minutes" call:
`GET /purchase-history` already answers it.

**B.3 GET calls that change data.** New POST/DELETE twins, same URLs and bodies:

| Was GET | Now also |
| :--- | :--- |
| `auth/logout` (it also clears the device token) | POST |
| `auth/account-deletion` | POST |
| `wishlists-add-product`, `wishlists-remove-product` | POST (body `product_id`) |
| `user/shipping/delete/{address_id}` | POST or DELETE |

GET is still accepted on the same URLs, only because the old Flutter app calls them that way. They go when it is
retired; the new app must use the new methods. Found by scanning every GET route's action for writes. Also
still GET and writing, not used by the new app: `delivery-boy/cancel-request/{id}`, `seller/product/delete/{id}`,
`seller/product/duplicate/{id}`, `seller/coupon/delete/{id}`, `seller/file/delete/{id}`. The payment starters
(`bkash/*`, `nagad/*`, `sslcommerz/begin`, `iyzico/init`) start a gateway payment on GET or ANY; they are
gateway redirects and I left them.

**B.4 Tax.** Decided by the owner: **VAT is added on top** of the price. Done in the app API:

- Per line, VAT = line price (price x quantity, or the rep's own price) x the product's VAT percent
  (`products.tax`, 9 or 21), rounded to cents. A product with explicit tax rows keeps using those.
- `GET /cart-summary`: `tax` is the sum of the line VAT, and `grand_total` and `grand_total_value` include it
  (`sub_total` stays the price before VAT).
- `POST /carts` lines: `tax_percent` (9 or 21) and `tax_amount` (money for the whole line). `tax` stays the
  percent for the legacy app; the new app should read the two new fields.
- `POST /order/store`: `grand_total = subtotal + VAT + shipping - coupon`. The money is stored in the new column
  `order_details.tax_amount` (migration `2026_11_03_090000`); `order_details.tax` keeps the percent, which every
  other writer already puts there.
- `GET /purchase-history-details/{id}`: `tax` is the VAT money, `subtotal` is before VAT, `grand_total` is the
  sum. `GET /purchase-history-items/{id}`: per line `tax` (money) and `tax_percent`.

The web storefront checkout, its cart summary, the coupon maths and the back office order entry follow the same
rule: VAT on top, recorded in `order_details.tax_amount`. In the back office, editing an order's line price
recomputes that line's VAT, and a line placed before VAT was recorded (amount 0) keeps its total when edited.
Orders placed before this change show `tax` 0.00. The offers (`OfferService`) still record only the percent.

**B.5 Minimum order.** `POST /order/store` does not refuse a small order. With `minimum_order_amount_check` on,
a cart below `minimum_order_amount` gets the delivery-fee product (`default_specific_product`) added as a line, and
the order is placed. `GET /delivery-info` reports `minimum_order_amount` and `default_delivery_fees` per seller;
`GET /cart-summary` reports `delivery_cost`. Note that `delivery-info` compares each seller's own lines and
`order/store` compares the whole cart. If you want a refusal instead of a fee, say so.

**B.6 Prices for unverified accounts.** Done, on my recommendation (you left the rule to me). Hidden from a
**guest** and from a **customer whose `admin_verified` is false**; staff, sellers, drivers and representatives see
prices. The flag a client reads is `admin_verified` in the user object. Where:

- Catalogue answers (product lists, search, featured, detail, variants and variant price, offers inside
  them, shop and flash-deal products, wishlist): `stroked_price`, `main_price`, `discounted_price`,
  `discounted_value`, `base_price`, `base_discounted_price`, `price_per_piece`, `calculable_price`, `price`,
  `unit_price` are `null`.
- Cart answers (`cart-summary`, `POST /carts`, `delivery-info`): the money fields (`sub_total`, `tax`,
  `tax_amount`, `shipping_cost`, `discount`, `grand_total`, `grand_total_value`, `delivery_cost`, `price`,
  `rep_price`, `subtotal`, `default_delivery_fees`) are `null`.
- Every such answer carries the header `X-Prices-Hidden: 1`, and an object answer also has `"price_hidden": true`.
  The product data (name, photos, stock, percent fields) is unchanged.

An unverified customer cannot place an order: `order/store` and `payments/pay/*` answer **403**
`{"combined_order_id":0,"result":false,"message":"Your account has not been verified yet..."}` (they could not see
what they would pay). They can still browse and fill a cart. This is also the answer to BR-20261102-B.5 (c).
The hidden-key lists live in
`HidePricesFromUnverified::KEYS`; a new money field in an answer has to be added there. The money fields of the
offers that the cart adds, and the order and invoice answers, are shown to the account that owns the order.

**B.7 Error shapes.** Shapes today:

| Case | Status | Body |
| :--- | :--- | :--- |
| Validation (sign-up, profile update, order delivery date, update-note, customer paging, device token, idempotency key) | 422 | `{"result":false,"message":"Validation Error"\|first message,"errors":{"field":["..."]}}` |
| Order refused (address, payment type, profit) | 422 | `{"combined_order_id":0,"result":false,"message":"..."}`; the delivery date refusal adds `errors` |
| Ownership | 403 for a rep-only call; 404 for a record that is not yours (cart line, order) | `{"result":false,"message":"..."}` |
| Throttling | 429 | Laravel's `{"message":"Too many attempts."}` |
| Version block | 426 | `{"message","error_code":"app_version_outdated","minimum_version":"x.y.z"}` |

Changed now: `POST /auth/signup` answers 422 (it was 200), and **a client that sends the header
`X-Status-Mode: http` gets 422 instead of 200 for every `result:false` answer below, with the same body**. Clients
without the header (the legacy app) are unchanged, so nothing has to be moved together. 401, 403, 404, 426 and
429 are untouched. The new app should send the header on every call. Without the header, 200 with `result:false`
still happens on at least one path
(found by scanning the controllers): `carts/add`, `change-quantity`, `process` and `update_item_price`;
`order/store` for stock, coupon, wallet and empty-cart refusals; `auth/login`, `auth/confirm_code`, `social-login`;
`coupon-apply`, `pick-customer`, `create-customer`, `wishlists` destroy, `profile/update` (its non-validation
failures), `profile/update-image`, `profile/image-upload`, `file/image-upload`, `reviews/submit`, `address` calls
(`deleteShippingAddress`, `updateAddressInCart`, `updateShippingTypeInCart`), `auction/place-bid`, the payment
gateway starters and `representatives/payments/store`.
---

## BR-20261102-B

**B.1 Read-only user call.** `GET /auth/user` now has `rep_has_package`, `tax_number` and
`bank_account_number` (see BR-20261103-B.1). `POST /auth/info` in this code returns the access token it was
given and creates no row (`tests`: token count unchanged); if dev creates a token per call, dev runs an older
build.

**B.2 Terms, privacy, about.** Two causes. In this app the storefront lives under `/temp`: the pages are
`/temp/terms`, `/temp/privacy-policy` and `/temp/mobile-page/{slug}` (the seeded slugs are `terms`,
`privacy-policy`, `about-us`). Staging serves the root paths because it runs the old store. And to take the URL out of the app:
`GET /policies/terms` and `GET /policies/privacy` now return `{"data":[{"content":"<html>"}]}`, like the
existing `policies/return|support|seller`. The about page has no API call; use `/temp/mobile-page/about-us`.

**B.3 Validation status.** Same question as BR-20261103-B.7. `POST /auth/signup` is 422 now.

**B.4 Reset-code test account.** I will not add a fixed code: it would let anyone reset any dev account's
password, and dev holds copies of real customers. Alternatives that need no code change: set `MAIL_MAILER=log`
on dev and read the code from `storage/logs/laravel.log`, or give me an inbox address you can read and I will
send the reset there.

**B.5 Sign-up and verification.** (a) Yes: `admin_verified` in the user object. (b) The store back office,
Customers, **Verify** (`POST customers/verify/{customer}`) toggles it. (c) No: an unverified customer's order is refused with 403 (see BR-20261103-B.6).

---

## BR-20261102-D

**D.1 Firebase project.** The code sends with `FIREBASE_PROJECT` and the service-account file named in
`FIREBASE_CREDENTIALS` (`config/services.php`, `fcm`, default `albarakabv-store`); I cannot read the server's `.env`, so I cannot say which
project it is: run `php artisan store:send-test-push <token>`, which prints it. A push to a token works if the
token belongs to the **same Firebase project** as that credential; a Firebase project can hold several Android apps, so registering `com.newstore.app.dev`,
`.staging` and the release id in "albarakabv-store" is enough, with no change on our side. Different project: the credential has to change.

**D.2 Device token.** Field `device_token`. `POST /profile/update-device-token` body `{"device_token":"..."}`
(max 255 characters; `firebase_token` is read as a second name; an empty value clears it). `POST /auth/login`,
`/auth/signup` and `/auth/info` also store `device_token` (or `firebase_token`). `device_key` is a separate old-app
field; the new app does not need it. One token per account: a second phone replaces the first.

**D.3 Events and payload.** Fixed. Every push now goes through one service on Firebase's **v1** API
(`FcmPushService`). Before, the order and status pushes used Google's legacy endpoint (`fcm/send` with a server
key), which Google has shut down, so none of them arrived, and an order placed through the API sent none at all.
Events that push to the customer's `device_token`, when the business setting `google_firebase` is on:

| Event | Title and body (language of the customer) | `data` |
| :--- | :--- | :--- |
| Order placed through the app (cash on delivery, wallet or manual payment) | "Order placed!" / "An order :code has been placed" | `item_type` = `order`, `item_type_id` = order id |
| Delivery status changed (seller or back office) | "Order updated" / "Your order :code has been updated: :status" | same |
| Payment status changed | same | same |

Every push also carries `click_action` = `FLUTTER_NOTIFICATION_CLICK`. Drivers get a push when a trip is assigned
or an order on their trip changes (`item_type` is not set; `link` when given). A token Firebase reports as unknown
is cleared from its account so it is not tried again. The wording is in `lang/*/delivery.php` and `messages.php`.

**D.4 Test push.** `php artisan store:send-test-push <device token> [--title=] [--body=]` on the server. It prints
the Firebase project it sends with, and says whether the token was accepted, not known to that project, or the
push could not be sent (the reason is in `storage/logs/laravel.log`). Send me a token from `com.newstore.app.dev`
and, with access to the server, this is also the check for D.1.

**D.5 Sign-out and deletion.** `POST /auth/logout` (and the old GET) now clears the stored token, so a signed-out
phone gets no pushes. Deleting the account deletes the row with its token. `POST
/profile/update-device-token` with an empty token also removes it.

---

## BR-20261101-A

**1. Clearing a note.** Done. `POST /carts/add` with `item_notes` of `""`, `null` or only spaces clears the
note; the key absent leaves it. A note is trimmed. Also `POST /carts/update-note {"id": <cart line id>,
"item_notes": ""}` (key required; own lines only, 404 otherwise).

**2. A line per note.** Opinion, not built. Cost is medium. `carts` has no unique key on user, product
and variant (only plain indexes), so no migration is forced; the one-line rule lives in code: `add` finds
the line by `(user, product, variation)` through `updateOrCreate`, and `process`, `change-quantity`, the offers
logic and the min/max checks all assume it. Changing the key to `(user, product, variation, trimmed note)` means
rewriting `add`, summing the lines for the min and max limits, the stock check against the sum, merging lines
whose notes become equal, and the offer processing (`processOffer`, which works per line). The order and its lines
already carry `item_notes`. I would ship point 1 first and do this as its own change with the app team,
about two days with tests.

---

## BR-20261103-C

**C.1 Paged customers.** Done. `GET /representatives/get-customers?page=&per_page=` (default 50, at most 100)
answers `{"data":[{id,name,otajerID,company_name}],"meta":{"current_page","last_page","per_page","total"}}`.
The filters `name`, `otajer_id` and `company_name` were already applied in the query (not on the phone); they
combine with paging. Without `page` or `per_page` the whole list still comes back, for the old app.

**C.2 Thumbnails.** Done. `thumbnail_image` in the product list, search, detail and wishlist answers is a copy
about 400 px wide (WebP; JPEG if the server lacks WebP) made the first time it is asked for and stored in
`public/uploads/thumbs`. `photos[].path` is still the original. Uploads on S3 or with an external link, and files
that cannot be resized, keep the original URL. The first request for a new product pays the resize once. A
bulk pre-generation command is not built; say if you want one before launch.
