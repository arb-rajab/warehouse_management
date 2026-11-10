# Payment gateway sandbox checklist (staging)

Run this on **staging with each gateway's sandbox keys** before that gateway is ever enabled in production.
It was written from the code (`app/Http/Controllers/Payment/*`, `app/Services/*Gateway*`, `config/store.php`,
`bootstrap/app.php`), and from a local run of checkout with simulated callbacks. No real gateway or gateway
sandbox has been called yet, so every "expected" below is what the code says should happen, not something
already observed against a provider.

Mark each check `[x]` pass, `[!]` fail (write what you saw), `[-]` not applicable. Capture the evidence listed in section 6.

## 0. Rules

- **Staging only. Sandbox keys only. Never a live key, never a real card.** A live key on staging charges real money.
- Nothing is enabled by default. A gateway is on only when **both** its name is in `STORE_PAYMENT_GATEWAYS` **and** its store setting is `1` (section 2).
- Test one gateway at a time. After each, remove it from `STORE_PAYMENT_GATEWAYS` again (the rollback in section 7).
- Use a throwaway customer account and a cheap in-stock product. Note the starting stock so you can check it afterwards.
- The server running staging must be reachable by the gateway: **public HTTPS** with a valid certificate (gateway servers POST to it).

## 1. Fix these before testing (found while preparing this list)

These are defects in the current code. Testing the gateway before they are fixed will fail for reasons that are not the gateway's.

**Fixed (verify them on staging, do not assume):**
- [x] **SSLCommerz return URLs had no `/temp`.** They are now `route('sslcommerz.success|fail|cancel')`, i.e. `https://<host>/temp/sslcommerz/...`. The mobile API's URLs were also wrong (`/api/v1/sslcommerz/...`, missing `/store`) and are now `route('api.sslcommerz.*')`. On staging: after paying, the customer must land on a store page, never a 404.
- [x] **SSLCommerz charged the store's figure in `BDT`.** The amount is now converted from the store's default currency to the gateway's (`SSLCZ_CURRENCY`, default `BDT`) using the `currencies` table (`amount / default rate * gateway rate`), and the validation and the credit use the matching units. A gateway currency the store has no **active** rate for is refused with a message, never charged. **The store therefore needs an active `currencies` row with that code and its exchange rate before this gateway can take a payment.** On staging: the amount shown at SSLCommerz must equal the order total times that rate.
- [x] **Nagad's default callback URL had no `/temp`.** `NAGAD_CALLBACK_URL` is now unset by default and the callback is the storefront's `route('nagad.callback')`. Set it only to send Nagad somewhere else.

**Still open:**
- [ ] **N-Genius is wired to N-Genius's UAT test environment only, and has no production URL.** `NgeniusUtility::getMode()` has `$sandbox = false` hard-coded, but its "real" URLs are `identity-uat.ngenius-payments.com` and `api-gateway-uat.ngenius-payments.com`, and a comment says the sandbox URLs do not work. So it is safe to test with UAT credentials, and it can never take a real payment: going live needs a production switch and URLs. (An earlier version of this checklist said it always talks to the real N-Genius. That was wrong.) Its amounts are right: they are sent in minor units, rounded to the cent. Set `NGENIUS_CURRENCY` (default `AED`) to the store's currency.
- [ ] **Iyzico rounds every charge to a whole unit.** `IyzicoController` sends `round($grand_total)` for the price, so 24.60 is charged as 25. The credit check allows the same rounding, so nothing flags it. Sending the real amount to two decimals needs a code change.
- [ ] **Iyzico sends a made-up buyer to the provider on every payment.** The buyer name ("John Doe"), identity number, and the buyer, shipping and billing address (a street in Istanbul, Turkey) are hard-coded, and the locale is Turkish. Real customers' details are never sent, which matters for the provider's fraud checks and its terms.
- [ ] **Checkout does not offer gateways.** The payment-option card in `resources/views/shop/frontend/payment_select.blade.php` is `d-none` and every gateway radio is inside a `{{-- --}}` comment; only cash on delivery is live. To test through the UI, restore the markup for that gateway. Without it, you can only submit `payment_option=<gateway>` by hand (section 3, "No UI" note).
- [ ] **A gateway outage is a 500.** `bkash/create-payment` (and other gateways' first call) does not catch a failed request to the provider. Decide the customer-facing message before go-live.
- [ ] **Currencies and rounding per gateway.** Confirm the store currency against what each gateway charges (section 4). SSLCommerz now converts; no other gateway does. Iyzico, bKash, AamarPay and Nagad also round the charge to a whole unit (24.60 becomes 25), so check each one: unless its charge currency matches the store's, and whole-unit rounding is acceptable, it will charge the wrong sum.

PayHere (callbacks), Iyzico, AamarPay, bKash, VoguePay and now SSLCommerz and Nagad build their return URLs with `route()`, which carries `/temp`.

## 1b. Which gateway first? (a recommendation, from reading the code)

Read before choosing. The store prices in **euros** and serves customers in the Netherlands; most of these gateways were built for other markets and currencies.

| Gateway | Built for | Blocker for a euro store |
| --- | --- | --- |
| bKash, Nagad, SSLCommerz, AamarPay | Bangladesh (BDT mobile money and cards) | Charge in BDT. SSLCommerz now converts from the store currency. bKash, Nagad and AamarPay send the store's own figure **rounded to a whole unit** with no conversion, and Nagad hard-codes the BDT currency code (`050`) |
| PayHere | Sri Lanka (LKR) | Sends the store's figure unconverted under the currency in `PAYHERE_CURRENCY`: correct only if that equals the store's currency |
| VoguePay | Nigeria | Sends the store's own currency code, so there is no mismatch, but VoguePay must accept it. The merchant id is `VOGUE_MERCHANT_ID`, set in the environment |
| Iyzico | Turkey (TRY) | Whole-unit rounding and a fake Turkish buyer on every payment (section 1) |
| N-Genius | UAE and the Middle East (AED default) | Amounts are right and the currency is configurable, but it is wired to the UAT test environment with no production URL (section 1) |

- **Recommended now: enable none of them.** Cash on delivery works end to end (order, stock, cancel and restock were checked), and the gateway code paths were exercised only with simulated callbacks.
- **If online payment is needed for Dutch customers, integrate a euro-native provider as a new gateway** rather than reviving these. That needs a provider decision, a contract and keys first, so it is a business choice, not a code task.
- **If one of the existing gateways must be used, N-Genius is the closest:** its amounts are correct, its currency is configurable, and it can be tested safely against UAT. It still needs a production/UAT switch before it can be live.
- **Iyzico is the furthest:** it needs real buyer data and decimal amounts first.
- Until a gateway is chosen, do not restore the checkout markup, add an outage message, or add a sandbox switch for the others: each of those is wasted work on a gateway that will not be used.

## 2. Enabling one gateway on staging

- [ ] Deploy a build that contains PR #555 (CSRF exemptions for the callbacks under `/temp`). Without it, every POST callback answers 419.
- [ ] Set the gateway's env keys (section 4) in staging's `.env`, then `php artisan config:clear`.
- [ ] Add the gateway name to `STORE_PAYMENT_GATEWAYS` (comma separated: `payhere`, `sslcommerz`, `voguepay`, `bkash`, `nagad`, `iyzico`, `ngenius`, `aamarpay`).
- [ ] In the back office set the matching setting to `1` (`payhere`, `sslcommerz_payment`, `voguepay`, `bkash`, `nagad`, `iyzico`, `ngenius`, `aamarpay`) and the `<gateway>_sandbox` setting where it exists.
- [ ] Register the gateway's callback URLs in its merchant dashboard (section 4). They must be `https://<staging-host>/temp/...`.
- [ ] Confirm the routes answer: before enabling, `GET /temp/payhere/checkout/cancel` (any gateway route) must be **404**; after enabling, it must **not** be 404.
- [ ] Confirm the store's currency, and that the test product's price in the gateway's currency is at or above the gateway's minimum charge.

## 3. The shared test, run for every gateway

**Happy path: cart payment**
- [ ] As the test customer add the product to the cart and go to payment. Note the cart total.
- [ ] Choose the gateway (see "No UI" below if the radio is hidden) and complete the order step.
- [ ] You are redirected to the gateway's sandbox page, and the amount and currency shown there equal the order total in the gateway's currency.
- [ ] Pay with the gateway's sandbox success credentials.
- [ ] You land on the store's order confirmation page (`/temp/checkout/order-confirmed`) and see "Thank You for Your Order".
- [ ] Database: the order has `payment_status = paid`, `payment_type` = the gateway, the order lines are paid, and the paid amount equals the order total.
- [ ] The cart is empty, and the variant's stock dropped by the quantity bought, once.
- [ ] The order appears in the customer's purchase history and in the back office orders list as paid.
- [ ] No email or notification was sent twice.

**Unhappy paths: nothing must be marked paid**
- [ ] Customer cancels on the gateway page: back on the store, order stays `unpaid`, cart or order state is sane, a clear message is shown, no 500.
- [ ] Failed payment (the sandbox's declined card): order stays `unpaid`, a failure message is shown.
- [ ] Customer closes the gateway tab and never returns: the order stays `unpaid` (an abandoned payment must not pay anything).
- [ ] **Replay:** capture a successful callback request (browser network tab, or the gateway dashboard's resend) and send it a second time. The order is not paid twice, stock and wallet are not changed again, and no second payment record appears.
- [ ] **Forged callback:** send the callback URL with made-up values and no valid signature or reference. Nothing is paid and the response is a refusal, not a 500.
- [ ] **Short payment:** if the sandbox lets you pay less than the total (or you change the amount in a forged request), the order is not marked paid.
- [ ] **Someone else's order:** log in as a second customer and call a callback naming the first customer's order. It must not pay it.
- [ ] The callback endpoints refuse a request made outside a payment (no session context), without a 500.

**Other payment types the gateway supports.** Per the controllers: every gateway handles cart payments and wallet top-ups; every gateway except PayHere and VoguePay also handles seller packages; customer packages are handled by all of them. Test only what the gateway supports:
- [ ] Wallet top-up: pay an amount, the customer's wallet balance rises by exactly that amount, once; a replay does not top up twice; one customer cannot top up another's wallet.
- [ ] Customer package purchase: the package is granted once.
- [ ] Seller package purchase (seller account): the package is granted once.

**No UI?** If you have not restored the gateway radios, sign in, build the cart, reach the payment step in the browser, then submit the final form with `payment_option=<gateway name>` set by hand (browser dev tools). The gateway name is the controller's prefix, lower case: `payhere`, `sslcommerz`, `voguepay`, `bkash`, `nagad`, `iyzico`, `ngenius`, `aamarpay`. The store refuses a gateway that is not in `STORE_PAYMENT_GATEWAYS` (it flashes "select a payment option").

## 4. Per gateway

Replace `<host>` with the staging host. All callback paths sit under `/temp`.

| Gateway | Env keys | Store settings | Currency in code | Callback URLs to register | How the store verifies |
| --- | --- | --- | --- | --- | --- |
| **PayHere** | `PAYHERE_MERCHANT_ID`, `PAYHERE_SECRET`, `PAYHERE_CURRENCY` | `payhere`, `payhere_sandbox` | `PAYHERE_CURRENCY` | `https://<host>/temp/payhere/checkout/{notify,return,cancel}`, `.../wallet/{notify,return,cancel}`, `.../customer_package_payment/{notify,return,cancel}` | MD5 signature (`md5sig`) rebuilt from the merchant secret; order id carries the account (`W<user>-…`, `P<user>-<package>-…`) |
| **SSLCommerz** | `SSLCZ_STORE_ID`, `SSLCZ_STORE_PASSWD`, `SSLCZ_CURRENCY` (default `BDT`) | `sslcommerz_payment`, `sslcommerz_sandbox` | `SSLCZ_CURRENCY`, converted from the store currency (needs an active `currencies` row) | `https://<host>/temp/sslcommerz/{success,fail,cancel}` (**POST**; built by the store with `route()`) | server-side `orderValidate()` of `val_id` for the converted amount due, in the gateway currency |
| **VoguePay** | `VOGUE_MERCHANT_ID` (the form sends `demo` instead when sandbox mode is on) | `voguepay`, `voguepay_sandbox` | from the form | `https://<host>/temp/vogue-pay/success/{id}`, `.../failure/{id}` (GET) | asks VoguePay's server for the transaction: approved, same id, same `merchant_ref` as this session, covers the amount due |
| **AamarPay** | `AAMARPAY_STORE_ID`, `AAMARPAY_SIGNATURE_KEY` | `aamarpay`, `aamarpay_sandbox` | store default | success `https://<host>/temp/aamarpay/success`, fail `.../fail` (**POST**); cancel returns to the cart | signature key check on the verification call |
| **Iyzico** | `IYZICO_API_KEY`, `IYZICO_SECRET_KEY`, `IYZICO_CURRENCY_CODE` (default `TRY`) | `iyzico`, `iyzico_sandbox` | `TRY` by default | `https://<host>/temp/iyzico/payment/callback/{payment_type}/{amount?}/{payment_method?}/{combined_order_id?}/{customer_package_id?}/{seller_package_id?}` (any method; built by the store, not registered by hand) | retrieves the payment by `token` from iyzico and accepts only the one reported for this basket |
| **bKash** | `BKASH_CHECKOUT_APP_KEY`, `BKASH_CHECKOUT_APP_SECRET`, `BKASH_CHECKOUT_USER_NAME`, `BKASH_CHECKOUT_PASSWORD` | `bkash`, `bkash_sandbox` | BDT | `https://<host>/temp/bkash/callback` (GET) then `/temp/bkash/success` | bKash's own execute call, or its status query if execute does not answer; one payment per session |
| **Nagad** | `NAGAD_MODE` (`sandbox`), `NAGAD_MERCHANT_ID`, `NAGAD_MERCHANT_NUMBER`, `NAGAD_MERCHANT_PRIVATE_KEY`, `NAGAD_PG_PUBLIC_KEY`, `NAGAD_CALLBACK_URL` (optional) | `nagad` | BDT | `https://<host>/temp/nagad/callback` (GET); the store sends it by default, nothing to register by hand | signed request and response (`sensitiveData` + `signature`); one Nagad order per session |
| **N-Genius** | `NGENIUS_API_KEY`, `NGENIUS_OUTLET_ID`, `NGENIUS_CURRENCY` (default `AED`) | `ngenius` | `AED` by default | `https://<host>/temp/ngenius/{cart,wallet,customer_package,seller_package}_payment_callback` | retrieves the order by `ref` from N-Genius. **UAT only, no production URL** (section 1) |

Gateway-specific checks:
- [ ] **PayHere:** the notify URL is called server to server by PayHere. Confirm it arrives (PayHere dashboard or staging access log), answers 200, and that the signature check rejects a forged `md5sig`.
- [ ] **PayHere:** the `/temp/payhere/*/testing` routes exist while the gateway is enabled. Do not leave PayHere enabled on a public site while they are present.
- [ ] **SSLCommerz / AamarPay:** the success and fail URLs receive a **POST from the customer's browser**. Confirm it is not 419 (section 2, PR #555) and that you land on a store page afterwards.
- [ ] **SSLCommerz:** the amount at the gateway is the order total converted at the store's rate for `SSLCZ_CURRENCY`, and the credited wallet or order amount is in the store currency. Also refuse-test it: with no active rate for the currency, paying must stop with a message and send nothing to the gateway.
- [ ] **Iyzico:** the token in the callback is for this basket; a token from another basket is refused.
- [ ] **bKash:** a sandbox "success", "failure" and "cancelled" each end in the right state; a bKash outage shows a message, not a 500.
- [ ] **Nagad:** both success and failure of the signed response leave the order in the right state.
- [ ] **VoguePay:** open `/temp/vogue-pay/success/<an approved transaction id from another session>` while signed in: it must be refused, since the `merchant_ref` belongs to this session.

## 5. Whole-store checks while a gateway is on

- [ ] A normal POST without a CSRF token is still refused (419): e.g. submit the login form from another origin, or POST to `/temp/cart/addtocart` with no token.
- [ ] Cash on delivery still works end to end.
- [ ] The back office order list and the customer's purchase history show gateway orders with the right payment status.
- [ ] `storage/logs/laravel.log` shows no unexpected `5xx`, and no stack trace from a callback.
- [ ] Reconcile at the end: every order shown as paid in the store matches a successful transaction in the gateway's sandbox dashboard, and the sums are equal.

## 6. Evidence to keep for each gateway

For the sign-off, attach or note:
- the gateway sandbox transaction id and the store order id for the happy path;
- the order row and its lines after payment (`payment_status`, `payment_type`, `grand_total`);
- the stock before and after;
- the replay and forged-callback requests and their responses;
- the result of every unchecked box above with the date and who ran it.

| Gateway | Date | Tester | Happy path | Cancel / fail | Replay / forged | Wallet / packages | Result |
| --- | --- | --- | --- | --- | --- | --- | --- |
| PayHere | | | | | | | |
| SSLCommerz | | | | | | | |
| VoguePay | | | | | | | |
| AamarPay | | | | | | | |
| Iyzico | | | | | | | |
| bKash | | | | | | | |
| Nagad | | | | | | | |
| N-Genius | | | | | | | |

## 7. Rolling a gateway back

- [ ] Remove the gateway from `STORE_PAYMENT_GATEWAYS` and run `php artisan config:clear`. Its routes answer 404 again and the checkout refuses it.
- [ ] Set its store setting to `0`.
- [ ] Orders already marked paid stay paid; check them against the gateway before refunding or fulfilling.
- [ ] Unpaid orders left by abandoned payments can be cancelled from the customer's purchase history (stock is restored once).

Do not enable a gateway in production until every box in sections 1 to 5 is checked for it on staging.
