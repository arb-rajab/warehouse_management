<?php
namespace App\Http\Controllers;

use App\Services\GatewayPaymentService;
use App\Services\PaymentOptions;
use App\Services\VerifiedPayment;
use App\Models\Wallet;
use Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Session;

class WalletController extends Controller
{
    public function index()
    {
        $wallets = Wallet::where('user_id', Auth::user()->id)->latest()->paginate(10);
        return view('shop.frontend.user.wallet.index', compact('wallets'));
    }

    public function recharge(Request $request)
    {
        if (! app(PaymentOptions::class)->isAllowed((string) $request->payment_option)) {
            flash(__('storefront.checkout.select_payment_option'))->warning();
            return back();
        }

        $data['amount'] = $request->amount;
        $data['payment_method'] = $request->payment_option;

        $request->session()->put('payment_type', 'wallet_payment');
        $request->session()->put('payment_data', $data);

        $request->session()->put('payment_type', 'wallet_payment');
        $request->session()->put('payment_data', $data);

        $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
        if (class_exists($decorator)) {
            return (new $decorator)->pay($request);
        }
    }

    /**
     * Where a gateway's callback finishes a wallet top-up, once its server has
     * confirmed it. The amount is what the gateway confirmed it took, not the
     * amount the session asked for, and a repeated callback adds nothing
     * (GatewayPaymentService).
     */
    public function wallet_payment_done(VerifiedPayment $payment): RedirectResponse
    {
        $payerId = Auth::id();

        Session::forget('payment_data');
        Session::forget('payment_type');

        if ($payerId === null || ! app(GatewayPaymentService::class)->complete($payment, (int) $payerId, GatewayPaymentService::WALLET)) {
            flash(__('storefront.checkout.payment_could_not_be_verified'))->error();
            return redirect()->route('wallet.index');
        }

        flash(__('storefront.checkout.recharge_completed'))->success();
        return redirect()->route('wallet.index');
    }
}
