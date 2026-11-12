---
paths:
  - 'app/Http/Controllers/Payment/**'
  - 'app/Services/*Gateway*'
  - 'app/Services/IyzicoPayments.php'
  - 'app/Http/Controllers/GatewayPaymentLandingController.php'
  - 'routes/shop-gateway-returns.php'
---

# Gateway returns: a stored reference plus the provider's verification, never the session

## A gateway return is authenticated by its stored reference and the provider, not by the session
With `SESSION_SAME_SITE=lax` (config/session.php) the browser sends no session cookie on a
cross-site POST. SSLCommerz (success, fail, cancel), AamarPay (success, fail) and the iyzico
storefront callback return the customer that way, so the session cannot say whose payment it
is. Until 2026-11-12 they read the pending payment from the session, credited nothing, and the
payment had to be reconciled by hand.

Now `pay()` records the payment server side with `App\Services\GatewayReturn::begin()` in
`wms_gateway_payments`, as pending, keyed by the reference sent to the gateway: `WEB-` plus 24
random characters (`GatewayReturn::newReference()`, inside SSLCommerz's 30) for SSLCommerz's and
AamarPay's `tran_id`, and iyzico's own checkout-form token for iyzico. The row holds the payer
(`user_id`), what is paid for, the store-currency amount, the converted `gateway_amount` and
`gateway_currency`, and `expires_at` (`config('store.gateway_return_ttl_minutes')`, 180).

On return the controller reads **only** the echoed reference (`tran_id`, `mer_txnid`, `token`)
and:
1. `GatewayReturn::find()` refuses an unknown reference, a row of another gateway, a row that is
   not a storefront one (the mobile API's rows have no `gateway_amount`), or an expired one,
   before any provider call. A completed row only redirects to its finished page.
2. re-runs the provider's server-side verification (SSLCommerz `orderValidate`, AamarPay's
   search API, iyzico `retrieve`);
3. `GatewayReturn::complete()` requires the provider's amount and currency to equal the recorded
   `gateway_amount`/`gateway_currency` to the cent (an overpayment is refused too); a mismatch
   leaves the row pending;
4. credits through `GatewayPaymentService::completePending()`: one transaction, the row locked and
   re-checked pending and unexpired, the existing sink (order ownership, not already paid,
   `covers()`), rolled back unless credited. Of two verified returns, the second finds the row
   completed and credits nothing.

Never take the owner, the amount, the type or the target from the request or the session on
this path. Keep iyzico's reference the token: the app's `iyzico/success` records completed rows
under the token too, and the shared `UNIQUE(gateway, reference)` is what stops one payment being
credited on the web and again through the API.

## The return routes run without the session, and answer with a 303 to a GET
A cross-site POST answered from the `web` group starts a fresh session, and its `Set-Cookie`
overwrites the customer's real cookie (a top-level navigation may set cookies): they were signed
out after every return. So `routes/shop-gateway-returns.php` is mounted under `/temp` outside the
`web` group (RouteServiceProvider): no session, no cookies, no CSRF check, `throttle:gateway-returns`
(AppServiceProvider: 30 a minute per IP, 10 a minute per `gateway|sha1(reference)`), and
`payment.gateway:<name>` from `guardPaymentGatewayRoutes()`. Nothing on that path may flash or
touch the session.

Every outcome is a 303 to a GET in the `web` group (`GatewayPaymentLandingController`), which
does carry the lax cookie:
- credited, or already completed: a signed `gateway_payment.finished` link valid 30 minutes. The
  signed-in payer goes where the session flow went (cart: `order_confirmed`; wallet: `wallet.index`;
  package: `dashboard`). Anyone else gets `shop.frontend.payment_received`, a neutral "payment
  received, sign in" page that names nothing about the payment (`url.intended` brings the payer back).
- refused: `gateway_payment.outcome/unverified`; fail and cancel: `.../failed`, `.../cancelled`.

A new gateway that returns with a cross-site POST follows the same pattern; do not add its path to
`preventRequestForgery(except:)` or route it through the `web` group. The CSRF list keeps only
`temp/payhere*`, `temp/bkash*` and `temp/apple-callback` (`GatewayCallbackCsrfTest` pins the list
and that only these six routes run without the `web` group).

## Logging and cleanup
`GatewayReturn::refuse()` logs a warning with the gateway, the reason (`unknown`, `wrong_gateway`,
`expired`, `not_verified`, `amount_mismatch`, `sink_refused`) and the reference cut to 6 characters
plus `…`; never the post, `val_id`, `verify_sign`, the full token or a credential. The daily
`model:prune` (routes/console.php) deletes only pending rows whose `expires_at` is more than 90 days
past (`GatewayPayment::prunable()`); completed rows are never pruned, and API rows (`expires_at`
null) are not touched.

## Payments in flight at a deploy, and the session gateways
There is no session fallback: a return whose reference has no row (started before the release) is
refused and logged `unknown`, and is reconciled by hand
(docs/qa/payment-gateway-sandbox-checklist.md, "Stuck, unreconciled payments"). bKash, Nagad,
N-Genius and VoguePay return with a top-level GET, which carries the lax cookie, and keep using
`SessionGatewayPayment`; PayHere credits from its signed notify. Tests: `GatewayReturnWithoutSessionTest`,
`LegacyGatewayPaymentCreditingTest`, `SslcommerzCurrencyAndUrlsTest`, `AamarpayGatewayTest`,
`IyzicoGatewayTest`, `GatewayCallbackCsrfTest`.
