<?php

namespace App\Http\Controllers\Api\V2\Seller;

use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use Illuminate\Http\Request;

class SellerPackagePaymentController extends Controller
{
    public function offline_payment_request(){
        $package_payment_requests = SellerPackagePayment::where('offline_payment',1)->orderBy('id', 'desc')->paginate(10);
        return view('manual_payment_methods.seller_package_payment_request', compact('package_payment_requests'));
    }

    public function offline_payment_approval(Request $request)
    {
        $package_payment    = SellerPackagePayment::findOrFail($request->id);
        $package_details    = SellerPackage::findOrFail($package_payment->seller_package_id);
        $package_payment->approval      = $request->status;
        if($package_payment->save()){
            $seller                                 = $package_payment->user->seller;
            $seller->seller_package_id              = $package_payment->seller_package_id;
            $seller->invalid_at                     = date('Y-m-d', strtotime( $seller->invalid_at. ' +'. $package_details->duration .'days'));
            if($seller->save()){
                return 1;
            }
        }
        return 0;
    }
}
