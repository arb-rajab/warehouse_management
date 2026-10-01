<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\WalletCollection;
use App\Models\StoreUser;
use App\Services\WalletPaymentService;
use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function balance()
    {
        $user = StoreUser::find(auth()->user()->id);
        $latest = Wallet::where('user_id', auth()->user()->id)->latest()->first();
        return response()->json([
            'balance' => format_price($user->balance),
            'last_recharged' => $latest == null ? "Not Available" : $latest->created_at->diffForHumans(),
        ]);
    }

    public function walletRechargeHistory()
    {
        return new WalletCollection(Wallet::where('user_id', auth()->user()->id)->latest()->paginate(10));
    }

    public function processPayment(Request $request, WalletPaymentService $wallet)
    {
        $user = auth()->user();
        $insufficient = [
            'result' => false,
            'combined_order_id' => 0,
            'message' => translate('Insufficient wallet balance')
        ];

        if ($user->balance <= 0) {
            return response()->json($insufficient);
        }

        $response = (new OrderController)->store($request, true);
        $decoded_response = $response->original;

        // The order is priced from the cart; its own total is what the wallet pays.
        if ($decoded_response['result'] == true && ! $wallet->charge($user, $decoded_response['combined_order_id'])) {
            return response()->json([...$insufficient, 'combined_order_id' => $decoded_response['combined_order_id']]);
        }

        return $response;
    }
}
