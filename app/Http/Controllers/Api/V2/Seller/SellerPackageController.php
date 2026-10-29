<?php

namespace App\Http\Controllers\Api\V2\Seller;

use App\Http\Resources\V2\Seller\SellerPackageResource;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use App\Models\Shop;
use App\Services\PaymentOptions;
use App\Utility\PayfastUtility;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Session;

class SellerPackageController extends Controller
{
    public function seller_packages_list()
    {
        if (addon_is_activated('seller_subscription')) {
            $seller_packages = SellerPackage::all();
            return SellerPackageResource::collection($seller_packages);
        }

        return $this->failed(__('api.package.unavailable'));
    }

    public function purchase_free_package(Request $request)
    {
        // One package id, used for the price check and for what is granted: the
        // check read `seller_package_id` and the grant `package_id`, so a free
        // package could be named for the first and a paid one for the second.
        $seller_package = SellerPackage::findOrFail($request->package_id);

        if ($seller_package->amount == 0) {
            seller_purchase_payment_done(auth()->user()->id, $seller_package->id, 0, 'Free Package', null);
            return $this->success(__('storefront.common.package_purchasing_successful'));
        } elseif (
            auth()->user()->shop->seller_package != null &&
            $seller_package->product_upload_limit < auth()->user()->shop->seller_package->product_upload_limit
        ) {
            return $this->failed(__('api.package.product_limit'));
        }

        return $this->failed(__('api.package.unavailable'));
    }

    public function purchase_package_offline(Request $request, PaymentOptions $paymentOptions)
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:seller_packages,id'],
            'payment_option' => ['required', 'string', 'max:100'],
            ...$paymentOptions->proofRules(),
        ]);

        if (! $paymentOptions->isAllowed($validated['payment_option'])) {
            return $this->failed(__('storefront.common.something_went_wrong'));
        }

        $seller_package = SellerPackage::findOrFail($validated['package_id']);

        return DB::transaction(function () use ($seller_package, $validated) {
            // The shop row serialises two submissions from one seller, so the
            // pending-payment check below cannot be raced past.
            $shop = Shop::query()->where('user_id', auth()->user()->id)->lockForUpdate()->first();

            if ($shop === null) {
                return $this->failed(__('storefront.common.something_went_wrong'));
            }

            if (
                $shop->seller_package != null &&
                $seller_package->product_upload_limit < $shop->seller_package->product_upload_limit
            ) {
                return $this->failed(__('api.package.product_limit'));
            }

            $alreadyPending = SellerPackagePayment::query()
                ->where('user_id', auth()->user()->id)
                ->where('seller_package_id', $seller_package->id)
                ->where('offline_payment', 1)
                ->where('approval', 0)
                ->exists();

            if ($alreadyPending) {
                return $this->failed(__('api.package.payment_pending'));
            }

            $payment = new SellerPackagePayment;
            $payment->user_id = auth()->user()->id;
            $payment->seller_package_id = $seller_package->id;
            $payment->payment_method = $validated['payment_option'];
            $payment->payment_details = $validated['trx_id'];
            $payment->approval = 0;
            $payment->offline_payment = 1;
            $payment->reciept = $validated['photo'] ?? null;

            $payment->save();

            return $this->success(__('api.package.offline_submitted'));
        });
    }
}
