<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\StoreWithdrawRequest;
use App\Models\SellerWithdrawRequest;
use App\Services\SellerWithdrawService;
use Auth;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SellerWithdrawRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $seller_withdraw_requests = SellerWithdrawRequest::where('user_id', Auth::user()->id)->latest()->paginate(9);
        return view('shop.seller.money_withdraw_requests.index', compact('seller_withdraw_requests'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreWithdrawRequest $request, SellerWithdrawService $withdrawals)
    {
        $validated = $request->validated();

        try {
            $withdrawals->request(Auth::user(), (float) $validated['amount'], $validated['message'] ?? null);
        } catch (DomainException $exception) {
            flash($exception->getMessage())->error();
            return back();
        }

        flash(__('seller.withdrawals.request_has_been_sent_successfully'))->success();
        return redirect()->route('seller.money_withdraw_requests.index');
    }
}
