<?php

namespace App\Http\Controllers\Api\V2\Seller;

use App\Http\Resources\V2\Seller\SellerWithdrawResource;
use App\Models\SellerWithdrawRequest;
use App\Services\SellerWithdrawService;
use DomainException;
use Illuminate\Http\Request;
use Response;

class WithdrawRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $seller_withdraw_requests = SellerWithdrawRequest::where('user_id', auth()->user()->id)->latest()->paginate(10);
        return SellerWithdrawResource::collection($seller_withdraw_requests);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, SellerWithdrawService $withdrawals)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $withdrawals->request(auth()->user(), (float) $validated['amount'], $validated['message'] ?? null);
        } catch (DomainException $exception) {
            return $this->failed($exception->getMessage());
        }

        return $this->success(translate('Request has been sent successfully'));
    }
}
