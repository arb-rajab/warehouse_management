<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * What the mobile app's signed-in customer is paying for at a gateway. The
 * app's gateway endpoints used to take the payer (`user_id`) and the amount
 * from the request too, and price nothing from the database: any account could
 * pay any order or package, at any price, and top up anyone's wallet.
 */
class ApiGatewayPayment
{
    /**
     * The payment the signed-in customer asked for: their own order, or a
     * package priced from the database (a top-up is the amount they asked to
     * add). Null when there is no such thing for them to pay.
     *
     * @return array{user_id: int, payment_type: string, combined_order_id: int|null, package_id: int|null, amount: float}|null
     */
    public function resolve(Request $request): ?array
    {
        $userId = Auth::id();
        $paymentType = (string) $request->input('payment_type');

        if ($userId === null) {
            return null;
        }

        $combinedOrderId = $paymentType === GatewayPaymentService::CART ? (int) $request->input('combined_order_id') : null;
        $packageId = in_array($paymentType, [GatewayPaymentService::CUSTOMER_PACKAGE, GatewayPaymentService::SELLER_PACKAGE], true)
            ? (int) $request->input('package_id')
            : null;
        $amount = app(GatewayPaymentService::class)->amountDue($paymentType, (int) $userId, $combinedOrderId, $packageId, (float) $request->input('amount'));

        if ($amount === null) {
            return null;
        }

        return [
            'user_id' => (int) $userId,
            'payment_type' => $paymentType,
            'combined_order_id' => $combinedOrderId,
            'package_id' => $packageId,
            'amount' => $amount,
        ];
    }
}
