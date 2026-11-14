<?php

namespace App\Http\Controllers;

use App\Models\GatewayPayment;
use App\Services\GatewayPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Where a gateway return sends the browser (App\Services\GatewayReturn): a
 * top-level GET in the `web` group, so the lax session cookie comes with it and
 * the customer lands signed in, on the page the payment would have reached
 * before. It credits nothing; the return already did, or refused.
 */
class GatewayPaymentLandingController extends Controller
{
    /**
     * The signed link to a completed payment. Its payer sees their order, wallet
     * or dashboard; anyone else, or a browser that is not signed in, a neutral
     * "payment received, sign in" page that names nothing about the payment.
     */
    public function finished(Request $request, string $payment): View|RedirectResponse
    {
        $record = GatewayPayment::query()->whereKey((int) $payment)->whereNotNull('completed_at')->first();

        if ($record === null || Auth::id() === null || (int) Auth::id() !== $record->user_id) {
            // After signing in, the payer comes back here while the link is valid.
            Session::put('url.intended', $request->fullUrl());

            return view('shop.frontend.payment_received');
        }

        Session::forget(['payment_type', 'payment_data']);

        if ($record->payment_type === GatewayPaymentService::CART) {
            Session::put('combined_order_id', $record->combined_order_id);

            return redirect()->route('order_confirmed');
        }

        if ($record->payment_type === GatewayPaymentService::WALLET) {
            flash(__('storefront.checkout.recharge_completed'))->success();

            return redirect()->route('wallet.index');
        }

        flash(__('storefront.common.package_purchasing_successful'))->success();

        return redirect()->route('dashboard');
    }

    /**
     * A return that failed, was cancelled, or could not be verified: the message
     * the gateway's own handler used to flash, and the page it went to.
     */
    public function outcome(string $outcome): RedirectResponse
    {
        if ($outcome === 'unverified') {
            flash(__('storefront.checkout.payment_could_not_be_verified'))->error();

            return redirect()->route('home');
        }

        Session::forget(['order_id', 'payment_data']);

        if ($outcome === 'cancelled') {
            flash(__('storefront.checkout.payment_cancelled'))->error();

            return redirect()->route('home');
        }

        flash(__('storefront.checkout.payment_failed'))->error();

        return redirect()->route('cart');
    }
}
