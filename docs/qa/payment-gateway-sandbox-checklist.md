# Payment gateway sandbox checklist (staging)

Run this on **staging with each gateway's sandbox keys** before that gateway is ever enabled in production.
It was written from the code (`app/Http/Controllers/Payment/*`, `app/Services/*Gateway*`, `config/store.php`,
`bootstrap/app.php`), and from a local run of checkout with simulated callbacks. No real gateway or gateway
sandbox has been called yet, so every "expected" below is what the code says should happen, not something
already observed against a provider.

Mark each check `[x]` pass, `[!]` fail (write what you saw), `[-]` not applicable. Capture the evidence listed in section 6.

## Needs your input

What only the store owner can provide or confirm. None of it is guessed in the code; each item is a value or a fact from a provider.

- [ ] **iyzico identity number.** Provide `IYZICO_IDENTITY_NUMBER` in `.env` (what iyzico requires for the buyer: a Turkish identity number, or the value iyzico confirms for buyers without one on your account). There is no default and no placeholder: until it is set iyzico refuses to start any payment, sandbox included. Ask iyzico which value is acceptable for a store whose customers are not Turkish. The Payment methods page (iyzico tab) shows whether it is set, and the dashboard setup checklist names it while iyzico is enabled and it is unset.
- [ ] **N-Genius live URLs.** `NGENIUS_LIVE_IDENTITY_URL` and `NGENIUS_LIVE_GATEWAY_URL` default to the endpoints the code was written with, which were never confirmed with N-Genius. Confirm both against N-Genius's documentation or your account manager before choosing `live` mode, and set them in `.env` if they differ. UAT (the default) is not affected. The N-Genius tab carries a note about this while the gateway is enabled in live mode.
- [ ] **AamarPay redirect.** The store now sends the customer to `paynow.php?track=...` with a GET redirect; the old code POSTed an empty form to it. Confirm in AamarPay's sandbox that the payment page opens from a GET, or tell us what AamarPay expects. The AamarPay tab carries a note about this while the gateway is enabled in live mode.
- [ ] **Which gateway will be used.** Every gateway is processable but only simulated callbacks have exercised the code. Say which gateway you hold a merchant account for, so its sandbox run (sections 1 to 5) is done first.
- [ ] **Stuck, unreconciled payments.** The store credits a payment only after the gateway's server confirms it for the amount due, once per gateway reference. A payment the gateway took but the store refused (wrong amount, a replay, a customer who closed the window before the return, a return more than 3 hours late) leaves the order unpaid. SSLCommerz, AamarPay and iyzico on the web, and SSLCommerz and Nagad in the mobile app, keep a pending row from the moment the payment starts; the other web flows keep no row until a payment is credited. List every payment the gateway shows as successful and compare it with what the store holds:
  - **Credited by the store:** `SELECT gateway, reference, user_id, payment_type, combined_order_id, package_id, amount, gateway_amount, gateway_currency, completed_at FROM wms_gateway_payments WHERE completed_at IS NOT NULL ORDER BY completed_at;`. `reference` is the id the gateway knows the payment by (SSLCommerz and AamarPay `tran_id`, `WEB-…` on the web and `AIZ-…` in the app; Nagad `orderId`, bKash `paymentID`, iyzico token, N-Genius order `ref`). A gateway transaction whose id is not in this list was never credited here.
  - **Started but never completed:** `SELECT id, gateway, reference, user_id, payment_type, combined_order_id, package_id, amount, gateway_amount, gateway_currency, expires_at, created_at FROM wms_gateway_payments WHERE completed_at IS NULL ORDER BY created_at;`. Look each `reference` up in the gateway dashboard: paid there (for `gateway_amount` in `gateway_currency`) means the customer owes nothing and the order or package must be settled by hand; not paid means an abandoned payment. Rows like these are kept 90 days after `expires_at`, then pruned; a completed row is never pruned.
  - **Stuck from before the 2026-11-12 release (SSLCommerz, AamarPay, iyzico on the web):** a payment started before that release has no row, and its return is refused (`laravel.log`: `Gateway return refused.` with `"reason":"unknown"` and the first 6 characters of the reference). Its `tran_id` was 20 random characters with no `WEB-` prefix (iyzico: the token). Find the transaction in the gateway dashboard by that prefix and the time of the log line, take the customer and the order (or top-up, or package) from the gateway's record, check the order is still `unpaid` (below), and settle or refund it by hand, writing the gateway transaction id into the order's payment details. Before that release every SSLCommerz, AamarPay and iyzico web return was refused this way (the session was missing), so list the gateway's successful transactions for every date the gateway was enabled before it and check each one.
  - **Orders still unpaid whose customer paid:** take the gateway transaction's order or customer, find it with `SELECT id, combined_order_id, user_id, payment_type, payment_status, grand_total, created_at FROM orders WHERE payment_status = 'unpaid' AND payment_type = '<gateway>' ORDER BY created_at;`, and refund or settle it from the gateway side. Do not edit `payment_status` by hand without a gateway transaction id: record it as the order's payment details.
  - `storage/logs/laravel.log` names a refused payment (`payment could not be verified`, `refused`) with the gateway and type.

## 0. Rules

- **Staging only. Sandbox keys only. Never a live key, never a real card.** A live key on staging charges real money.
- Nothing is enabled by default. A gateway is on only when **both** its name is in `STORE_PAYMENT_GATEWAYS` **and** its store setting is `1` (section 2).
- Test one gateway at a time. After each, disable it again (remove it from `STORE_PAYMENT_GATEWAYS`, or switch it off on the Payment methods page when the list is chosen there; the rollback in section 7).
- Use a throwaway customer account and a cheap in-stock product. Note the starting stock so you can check it afterwards.
- The server running staging must be reachable by the gateway: **public HTTPS** with a valid certificate (gateway servers POST to it).

## 1. Fix these before testing (found while preparing this list)

These are defects in the current code. Testing the gateway before they are fixed will fail for reasons that are not the gateway's.

**Fixed (verify them on staging, do not assume):**
- [x] **SSLCommerz return URLs had no `/temp`.** They are now `route('sslcommerz.success|fail|cancel')`, i.e. `https://<host>/temp/sslcommerz/...`. The mobile API's URLs were also wrong (`/api/v1/sslcommerz/...`, missing `/store`) and are now `route('api.sslcommerz.*')`. On staging: after paying, the customer must land on a store page, never a 404.
- [x] **SSLCommerz charged the store's figure in `BDT`.** The amount is now converted from the store's default currency to the gateway's (`SSLCZ_CURRENCY`, default `BDT`) using the `currencies` table (`amount / default rate * gateway rate`), and the validation and the credit use the matching units. A gateway currency the store has no **active** rate for is refused with a message, never charged. **The store therefore needs an active `currencies` row with that code and its exchange rate before this gateway can take a payment.** On staging: the amount shown at SSLCommerz must equal the order total times that rate.
- [x] **Nagad's default callback URL had no `/temp`.** `NAGAD_CALLBACK_URL` is now unset by default and the callback is the storefront's `route('nagad.callback')`. Set it only to send Nagad somewhere else.

- [x] **PayHere charged the store's figure under `PAYHERE_CURRENCY` and never checked the notification's currency.** The signed form now carries the converted amount to two decimals and the currency, a currency with no active rate is refused, and a notification must be in the charge currency and match the converted amount due. On staging: the amount and currency at PayHere must equal the order total at the store's rate, and a wallet top-up must credit exactly the converted-back amount.

- [x] **Gateway credentials were read with `env()`, which is `null` under `config:cache`.** Production runs `artisan config:cache`, so SSLCommerz, bKash, Nagad, AamarPay and N-Genius would have sent empty keys on the live site (PayHere and Iyzico already used config). Every credential is now read from `config/services.php` / `config/nagad.php`, and a test fails if `env()` returns to gateway code. On staging: set the keys in `.env`, then run `php artisan config:cache` (the deploy does), and check a request really carries them.

- [x] **N-Genius had no way to go live, ignored the captured order's currency, and assumed N-Genius always answered.** `NGENIUS_MODE` now selects the environment: `uat` (the default, N-Genius's test environment) or `live`, with the URLs configurable (`NGENIUS_UAT_*_URL`, `NGENIUS_LIVE_*_URL`). **The live URLs are N-Genius's documented production endpoints from memory; check them against N-Genius's documentation before switching to `live`.** The amount due is converted to `NGENIUS_CURRENCY` (default `AED`; set it to `EUR` for a euro charge) and sent in minor units; the callback requires the captured order's currency and amount to match; an N-Genius that gives no payment page is refused with a message. On staging: run in `uat` with UAT credentials first.

- [x] **Iyzico rounded every charge to a whole unit and sent a made-up Turkish buyer on every payment.** The request now carries the customer's own name, email, phone, address, city, country, postcode and IP (a missing field is sent as `-`), the amount to the cent and converted to `IYZICO_CURRENCY_CODE` (default `TRY`; set `EUR` for a euro charge), the same price on the request, the paid price and the basket item, and an English locale unless the storefront is Turkish. The callback requires the charge currency and the converted amount due; an iyzico that gives no payment page is refused with a message. **iyzico requires an identity number for every buyer and the store holds none, so `IYZICO_IDENTITY_NUMBER` is one value the owner provides in `.env`. There is no default any more: the old placeholder `11111111111` was sent on every payment and is gone.** While it is unset iyzico refuses to start a payment (the customer sees "iyzico is not available right now" and `laravel.log` says `IYZICO_IDENTITY_NUMBER is not set in .env`). On staging: set it, run in sandbox and check the buyer shown in iyzico's dashboard is the real customer. See "Needs your input" below.

- [x] **bKash, Nagad and AamarPay rounded to whole units, sent the store's figure under a fixed BDT, and did not check the converted amount.** Each now converts the amount due (read from the database, with the payer's ownership check) to the gateway currency to two decimals and verifies the reported amount against the converted amount due, crediting the store-currency amount: `BKASH_CURRENCY`, `AAMARPAY_CURRENCY` and `NAGAD_CURRENCY` (all default `BDT`; Nagad accepts BDT only, so anything else is refused). A currency with no active rate, or a provider that does not answer with a payment page, is refused with a message and nothing is kept. The mobile API's bKash and Nagad flows get the same treatment. bKash's completed payment must also report the charge currency.
- [x] **Nagad changed PHP's timezone for the rest of the request.** Its constructor set the default timezone to Asia/Dhaka, skewing every timestamp the app wrote after it. Nagad's protocol timestamps are now made in Dhaka time without touching the default. Nagad's POST helper also had no timeout and now has one.
- [x] **AamarPay used raw curl and ended the request with `exit`.** It now uses the HTTP client with a timeout and returns a redirect to aamarpay's payment page instead of an auto-submitting form (a GET redirect to `paynow.php?track=...`). **Confirm in aamarpay's sandbox that the payment page accepts a GET redirect; the old code POSTed an empty form to it.** A response that is not a plain path to a payment page is refused.

**Open items (all done):**
- [x] **Checkout did not offer gateways.** The payment step, the wallet top-up and the customer packages page now list every gateway that is in `STORE_PAYMENT_GATEWAYS` **and** switched on in the back office (`PaymentOptions::available()`). With none enabled the pages are as before: cash on delivery only, and the gateway card stays hidden. The first gateway is preselected when any is offered.
- [x] **A gateway outage was a 500.** A provider call that times out or cannot connect (`ConnectionException`) is now handled globally in `bootstrap/app.php`: the store returns the customer to the cart with "Payment failed", the API answers 503. The exception is still reported. Nothing is charged or marked paid on that path. A provider that answers with an error is handled per gateway.
- [x] **Currencies and rounding per gateway.** Every gateway now sends the exact amount converted from the store currency to its own charge currency (`<GATEWAY>_CURRENCY`), signs/verifies it in that currency, and refuses a currency with no active, non-zero row in `currencies`. A payment must cover the amount due to the cent (`GatewayPaymentService::covers`). **Each BDT, LKR, TRY, AED, NGN gateway needs an active `currencies` row with a rate before it is enabled.**

PayHere (callbacks), Iyzico, AamarPay, bKash, VoguePay and now SSLCommerz and Nagad build their return URLs with `route()`, which carries `/temp`.

## 1b. Which gateway first? (a recommendation, from reading the code)

Read before choosing. The store prices in **euros** and serves customers in the Netherlands; most of these gateways were built for other markets and currencies.

| Gateway | Built for | Blocker for a euro store |
| --- | --- | --- |
| bKash, Nagad, SSLCommerz, AamarPay | Bangladesh (BDT mobile money and cards) | Charge in BDT; the store figure is converted from the store currency and checked on return. Needs an active BDT row in `currencies` |
| PayHere | Sri Lanka (LKR) | Converted to `PAYHERE_CURRENCY` and signed with it; notifications must name that currency. Needs an active row for it |
| VoguePay | Nigeria | Charges in `VOGUE_CURRENCY` (empty = the store currency); the total is converted and checked on return. The merchant id is `VOGUE_MERCHANT_ID`; the old hard-coded `developer_code` was removed, so confirm VoguePay accepts the form without it |
| Iyzico | Turkey (TRY) | Exact converted amount; the buyer is the signed-in customer. `IYZICO_IDENTITY_NUMBER` has no default: iyzico refuses to start a payment until it is set (iyzico requires an identity number) |
| N-Genius | UAE and the Middle East (AED default) | Amounts and currency are right and `NGENIUS_MODE` switches UAT/live; check the live URLs (written from memory) before using `live` |

- **Recommended: enable only a gateway you have a merchant account for.** Every gateway is processable and the checkout offers it once enabled, but the code was exercised only with simulated callbacks. Run its sandbox checks first. Cash on delivery works end to end.
- **If online payment is needed for Dutch customers, integrate a euro-native provider as a new gateway** rather than reviving these. That needs a provider decision, a contract and keys first, so it is a business choice, not a code task.
- **Closest fit for a first test: N-Genius** (configurable currency, safe to test against UAT).
- **Iyzico** needs `IYZICO_IDENTITY_NUMBER` before it can start any payment, sandbox included.
- Until a gateway is chosen, do not restore the checkout markup, add an outage message, or add a sandbox switch for the others: each of those is wasted work on a gateway that will not be used.

## 2. Enabling one gateway on staging

- [ ] Deploy a build that contains PR #555 (CSRF exemptions for PayHere's and bKash's callbacks under `/temp`) and the 2026-11-12 gateway-return change (SSLCommerz, AamarPay and iyzico's returns run without the session and without a CSRF check; its migration adds `gateway_amount`, `gateway_currency` and `expires_at` to `wms_gateway_payments`, so the deploy must run `php artisan migrate`).
- [ ] Set the gateway's keys (section 4): either in staging's `.env`, then `php artisan config:cache` (the deploy runs it), or on the back office's Payment methods page (`/temp/admin/payment-method`; a Super Admin, or a role a Super Admin gave `payment_methods_configurations`, with the current password). A gateway saved on that page takes all its settings from there and none from `.env`; check its "Effective configuration" list says "Saved in admin" or "From .env" as intended.
- [ ] Enable the gateway: add its name to `STORE_PAYMENT_GATEWAYS` (comma separated: `payhere`, `sslcommerz`, `voguepay`, `bkash`, `nagad`, `iyzico`, `ngenius`, `aamarpay`), or on the Payment methods page choose "Choose here" and switch it on (that list then wins over `.env`).
- [ ] In Features activation set the matching switch to on (`payhere`, `sslcommerz_payment`, `voguepay`, `bkash`, `nagad`, `iyzico`, `ngenius`, `aamarpay`), and on the Payment methods page set the gateway's mode to Sandbox.
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
- [ ] The callback endpoints refuse a request for a payment nobody started (a made-up `tran_id`, `mer_txnid` or token), without a 500.

**Other payment types the gateway supports.** Per the controllers: every gateway handles cart payments and wallet top-ups; every gateway except PayHere and VoguePay also handles seller packages; customer packages are handled by all of them. Test only what the gateway supports:
- [ ] Wallet top-up: pay an amount, the customer's wallet balance rises by exactly that amount, once; a replay does not top up twice; one customer cannot top up another's wallet.
- [ ] Customer package purchase: the package is granted once.
- [ ] Seller package purchase (seller account): the package is granted once.

**No UI?** The payment step, wallet top-up and customer packages pages show a gateway once it is in `STORE_PAYMENT_GATEWAYS` and its back office setting is `1` (section 3). If it does not appear, one of those two is missing. You can still submit `payment_option=<gateway name>` by hand: the gateway name is the controller's prefix, lower case: `payhere`, `sslcommerz`, `voguepay`, `bkash`, `nagad`, `iyzico`, `ngenius`, `aamarpay`. The store refuses a gateway that is not in `STORE_PAYMENT_GATEWAYS` (it flashes "select a payment option").

## 4. Per gateway

Replace `<host>` with the staging host. All callback paths sit under `/temp`.

| Gateway | Env keys | Store settings | Currency in code | Callback URLs to register | How the store verifies |
| --- | --- | --- | --- | --- | --- |
| **PayHere** | `PAYHERE_MERCHANT_ID`, `PAYHERE_SECRET`, `PAYHERE_CURRENCY` (optional: empty means the store currency) | `payhere`, `payhere_sandbox` | `PAYHERE_CURRENCY`, converted from the store currency (needs an active `currencies` row unless it is the store's own) | `https://<host>/temp/payhere/checkout/{notify,return,cancel}`, `.../wallet/{notify,return,cancel}`, `.../customer_package_payment/{notify,return,cancel}` | MD5 signature (`md5sig`) rebuilt from the merchant secret; the notification's currency must be the one the store charges, and its amount must equal the converted amount due (orders, packages) or is converted back to the store currency (wallet top-ups); order id carries the account (`W<user>-…`, `P<user>-<package>-…`) |
| **SSLCommerz** | `SSLCZ_STORE_ID`, `SSLCZ_STORE_PASSWD`, `SSLCZ_CURRENCY` (default `BDT`) | `sslcommerz_payment`, `sslcommerz_sandbox` | `SSLCZ_CURRENCY`, converted from the store currency (needs an active `currencies` row) | `https://<host>/temp/sslcommerz/{success,fail,cancel}` (**POST**; built by the store with `route()`) | the post's `tran_id` must be a payment the store recorded when it started (not expired, not used); server-side `orderValidate()` of `val_id` and the signature; the validated amount and currency must equal the recorded converted amount to the cent |
| **VoguePay** | `VOGUE_MERCHANT_ID` (the form sends `demo` instead when sandbox mode is on) | `voguepay`, `voguepay_sandbox` | from the form | `https://<host>/temp/vogue-pay/success/{id}`, `.../failure/{id}` (GET) | asks VoguePay's server for the transaction: approved, same id, same `merchant_ref` as this session, covers the amount due |
| **AamarPay** | `AAMARPAY_STORE_ID`, `AAMARPAY_SIGNATURE_KEY` | `aamarpay`, `aamarpay_sandbox` | store default | success `https://<host>/temp/aamarpay/success`, fail `.../fail` (**POST**); cancel returns to the cart | the post's `mer_txnid` must be a payment the store recorded when it started; aamarpay's search API (store id and signature key) must report it `Successful` for the recorded converted amount and currency, to the cent |
| **Iyzico** | `IYZICO_API_KEY`, `IYZICO_SECRET_KEY`, `IYZICO_CURRENCY_CODE` (default `TRY`) | `iyzico`, `iyzico_sandbox` | `TRY` by default | `https://<host>/temp/iyzico/payment/callback/{payment_type}/{amount?}/{payment_method?}/{combined_order_id?}/{customer_package_id?}/{seller_package_id?}` (any method; built by the store, not registered by hand) | the posted `token` must be the one iyzico issued when the store started the payment (recorded then); retrieves the payment by that token from iyzico, which must report it paid for the recorded converted amount and currency, to the cent |
| **bKash** | `BKASH_CHECKOUT_APP_KEY`, `BKASH_CHECKOUT_APP_SECRET`, `BKASH_CHECKOUT_USER_NAME`, `BKASH_CHECKOUT_PASSWORD` | `bkash`, `bkash_sandbox` | BDT | `https://<host>/temp/bkash/callback` (GET) then `/temp/bkash/success` | bKash's own execute call, or its status query if execute does not answer; one payment per session |
| **Nagad** | `NAGAD_MODE` (`sandbox`), `NAGAD_MERCHANT_ID`, `NAGAD_MERCHANT_NUMBER`, `NAGAD_MERCHANT_PRIVATE_KEY`, `NAGAD_PG_PUBLIC_KEY`, `NAGAD_CALLBACK_URL` (optional) | `nagad` | BDT | `https://<host>/temp/nagad/callback` (GET); the store sends it by default, nothing to register by hand | signed request and response (`sensitiveData` + `signature`); one Nagad order per session |
| **N-Genius** | `NGENIUS_API_KEY`, `NGENIUS_OUTLET_ID`, `NGENIUS_CURRENCY` (default `AED`) | `ngenius` | `AED` by default | `https://<host>/temp/ngenius/{cart,wallet,customer_package,seller_package}_payment_callback` | retrieves the order by `ref` from N-Genius. `NGENIUS_MODE` (`uat` default, or `live`); check the live URLs before using `live` |

Gateway-specific checks:
- [ ] **PayHere:** the notify URL is called server to server by PayHere. Confirm it arrives (PayHere dashboard or staging access log), answers 200, and that the signature check rejects a forged `md5sig`.
- [ ] **PayHere:** the `/temp/payhere/{checkout,wallet,customer_package}/testing` developer forms answer only while PayHere is enabled, its mode is sandbox and the app is not in production (`EnsurePaymentGatewaySandbox`); otherwise they are a 404. On staging (not production) in sandbox mode each opens a signed test form; switch PayHere to live mode, or run with `APP_ENV=production`, and each must be a 404.
- [ ] **SSLCommerz / AamarPay / iyzico:** the success, fail and cancel URLs receive a **POST from the gateway's site** (cross-site). It carries no session cookie, so the store finds the payment by its reference and answers with a 303 redirect. Confirm: not 419; the order (or top-up, or package) is credited once; you land **still signed in** on the order confirmation page (wallet page after a top-up, dashboard after a package); a failed or cancelled payment lands on a store page with its message, still signed in.
- [ ] **SSLCommerz / AamarPay / iyzico, no session at the return:** start a payment, then clear the store's cookies (or sign out in another tab) before paying. After paying, the payment is still credited, and you land on "Payment received" with a sign-in button; signing in takes you to the order. Open the "Payment received" link from that browser while signed in as another customer: the same neutral page, naming no order or amount.
- [ ] **SSLCommerz / AamarPay / iyzico, references:** a return replayed after it was credited lands on the same page and credits nothing; a return more than 3 hours after the payment started is refused (`laravel.log`: `"reason":"expired"`) and the row stays pending for reconciling; the same reference posted to another gateway's return URL is refused (`wrong_gateway`).
- [ ] **AamarPay:** confirm in the sandbox that aamarpay's search API (`api/v1/trxcheck/request.php`) answers with a `currency` (or `currency_merchant`) field; the store requires it to be the charge currency and refuses the return otherwise.
- [ ] **SSLCommerz:** the amount at the gateway is the order total converted at the store's rate for `SSLCZ_CURRENCY`, and the credited wallet or order amount is in the store currency. Also refuse-test it: with no active rate for the currency, paying must stop with a message and send nothing to the gateway.
- [ ] **Iyzico:** the token in the callback must be the one iyzico issued when this payment started; a token from another payment, or one never issued to the store, is refused.
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
