<?php

namespace App\Http\Controllers;

use App\Services\PaymentOptions;
use App\Models\CustomerPackage;
use App\Models\StoreUser;
use Auth;
use Illuminate\Http\Request;
use Session;

class CustomerPackageController extends Controller
{
    public function purchase_package(Request $request)
    {
        $data['customer_package_id'] = $request->customer_package_id;
        $data['payment_method'] = $request->payment_option;

        $request->session()->put('payment_type', 'customer_package_payment');
        $request->session()->put('payment_data', $data);

        $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);

        if ($customer_package->amount == 0) {
            $user = StoreUser::findOrFail(Auth::user()->id);
            if ($user->customer_package_id != $customer_package->id) {
                return $this->purchase_payment_done(Session::get('payment_data'), null);
            } else {
                flash(__('storefront.common.you_can_not_purchase_this_package_anymore'))->warning();
                return back();
            }
        }

        if (! app(PaymentOptions::class)->isAllowed((string) $request->payment_option)) {
            flash(__('storefront.checkout.select_payment_option'))->warning();
            return back();
        }

        $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
        if (class_exists($decorator)) {
            return (new $decorator)->pay($request);
        }
    }

    public function purchase_payment_done($payment_data, $payment)
    {
        $user = StoreUser::findOrFail(Auth::user()->id);
        $user->customer_package_id = $payment_data['customer_package_id'];
        $customer_package = CustomerPackage::findOrFail($payment_data['customer_package_id']);
        $user->remaining_uploads += $customer_package->product_upload;
        $user->save();

        flash(__('storefront.common.package_purchasing_successful'))->success();
        return redirect()->route('dashboard');
    }
}
