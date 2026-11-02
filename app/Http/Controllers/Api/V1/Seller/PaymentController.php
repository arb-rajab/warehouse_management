<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Seller\SellerPaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    //
    public function getHistory(){
        $sellerId = auth()->user()->id;
        $payments = Payment::orderBy('created_at', 'desc')->where('seller_id',$sellerId)->latest()->paginate(10);;
        return  SellerPaymentResource::collection($payments);
    }
}
