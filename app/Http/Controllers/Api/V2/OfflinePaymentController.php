<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Order;
use App\Services\PaymentOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class OfflinePaymentController extends Controller
{
    public function submit(Request $request, PaymentOptions $paymentOptions)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_option' => ['required', 'string', 'max:100'],
            ...$paymentOptions->proofRules(),
        ]);

        if (! $paymentOptions->isAllowed($validated['payment_option'])) {
            return $this->somethingWentWrong();
        }

        return DB::transaction(function () use ($validated) {
            $order = Order::query()->lockForUpdate()->find($validated['order_id']);

            // The account that placed it (or the customer it was placed for), and
            // not once it is paid or already submitted: this call turns the order
            // into 'Submitted', and a second submission would overwrite the first
            // one's proof.
            if ($order === null || auth()->user()->cannot('view', $order) || in_array($order->payment_status, ['paid', 'Submitted'], true)) {
                return $this->somethingWentWrong();
            }

            $order->manual_payment_data = json_encode([
                'name' => $validated['name'],
                'amount' => $validated['amount'],
                'trx_id' => $validated['trx_id'],
                'photo' => $validated['photo'] ?? null,
            ]);
            $order->payment_type = $validated['payment_option'];
            $order->payment_status = 'Submitted';
            $order->manual_payment = 1;

            $order->save();

            return response()->json([
                'result' => true,
                'message' => translate('Submitted Successfully')
            ]);
        });
    }

    private function somethingWentWrong(): JsonResponse
    {
        return response()->json([
            'result' => false,
            'message' => translate('Something went wrong')
        ]);
    }
}
