<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\WalletCollection;
use App\Models\StoreUser;
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

    public function processPayment(Request $request)
    {
        $user = auth()->user();

        if ($user->balance <= 0) {
            return response()->json([
                'result' => false,
                'combined_order_id' => 0,
                'message' => translate('Insufficient wallet balance')
            ]);
        }

        // The order is priced from the cart, and its own total is what the wallet
        // pays: charged in the order's transaction, before the cart is emptied,
        // so a balance that falls short places no order and keeps the cart.
        return (new OrderController)->store($request, true);
    }
}
