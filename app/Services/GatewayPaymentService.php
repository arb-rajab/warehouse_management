<?php

namespace App\Services;

use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\SellerPackage;
use App\Models\Shop;
use App\Models\StoreUser;
use App\Models\Wallet;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Where every store payment gateway credits a payment: an order marked paid, a
 * wallet topped up, a package activated.
 *
 * The gateways' callbacks used to hand the order, the payer and the amount over
 * from the request (or from the session) and the sinks applied them as given,
 * as often as they were called: a forged or replayed callback marked any order
 * paid, topped up any wallet by any amount, and ran the commission again. Now a
 * callback passes only what the gateway's server confirmed (VerifiedPayment),
 * and this runs in one transaction under row locks: the gateway's reference is
 * recorded once (a second identical callback credits nothing), the order must
 * be the payer's, not already paid and without commission, and the amount due
 * is read from the database, never from the request or the session.
 */
class GatewayPaymentService
{
    public const string CART = 'cart_payment';

    public const string WALLET = 'wallet_payment';

    public const string CUSTOMER_PACKAGE = 'customer_package_payment';

    public const string SELLER_PACKAGE = 'seller_package_payment';

    /**
     * Whether `$paid` pays `$due`. The gateways that charge whole units send
     * `round($due)`, which can be up to half a unit less than the total.
     */
    public static function covers(float $paid, float $due): bool
    {
        return $due > 0 && $paid + 0.005 >= min($due, round($due));
    }

    /**
     * What `$userId` owes for `$paymentType`, read from the database; for a wallet
     * top-up, the amount they asked to add. Null when there is nothing they can
     * pay for (not their order, no such package, an unknown type).
     */
    public function amountDue(string $paymentType, int $userId, ?int $combinedOrderId, ?int $packageId, ?float $walletAmount = null): ?float
    {
        $due = match ($paymentType) {
            self::CART => CombinedOrder::query()->whereKey($combinedOrderId)->where('user_id', $userId)->value('grand_total'),
            self::WALLET => $walletAmount,
            self::CUSTOMER_PACKAGE => CustomerPackage::query()->whereKey($packageId)->value('amount'),
            self::SELLER_PACKAGE => SellerPackage::query()->whereKey($packageId)->value('amount'),
            default => null,
        };

        return $due !== null && (float) $due > 0 ? (float) $due : null;
    }

    /**
     * Credit a verified payment to `$userId` once. False when the reference was
     * already used, the order is not theirs or is already paid, or the amount
     * does not pay what is due; nothing changes then.
     */
    public function complete(VerifiedPayment $payment, int $userId, string $paymentType, ?int $combinedOrderId = null, ?int $packageId = null): bool
    {
        return $this->atomically(function () use ($payment, $userId, $paymentType, $combinedOrderId, $packageId): bool {
            GatewayPayment::create([
                'gateway' => $payment->gateway,
                'reference' => $payment->reference,
                'user_id' => $userId,
                'payment_type' => $paymentType,
                'combined_order_id' => $combinedOrderId,
                'package_id' => $packageId,
                'amount' => $payment->amount,
                'completed_at' => now(),
            ]);

            return $this->credit($payment, $userId, $paymentType, $combinedOrderId, $packageId);
        });
    }

    /**
     * Record a payment the mobile API started, for a callback that arrives with
     * no session or token to say whose it is. Everything the callback will
     * credit is fixed here, by the signed-in payer.
     */
    public function begin(string $gateway, string $reference, int $userId, string $paymentType, ?int $combinedOrderId, ?int $packageId, float $amount): GatewayPayment
    {
        return GatewayPayment::create([
            'gateway' => $gateway,
            'reference' => $reference,
            'user_id' => $userId,
            'payment_type' => $paymentType,
            'combined_order_id' => $combinedOrderId,
            'package_id' => $packageId,
            'amount' => $amount,
        ]);
    }

    /**
     * Credit the payment begin() recorded under `$payment`'s reference, once,
     * when the verified amount pays the amount recorded then.
     */
    public function completePending(VerifiedPayment $payment): bool
    {
        return $this->atomically(function () use ($payment): bool {
            $pending = GatewayPayment::query()
                ->where('gateway', $payment->gateway)
                ->where('reference', $payment->reference)
                ->whereNull('completed_at')
                ->lockForUpdate()
                ->first();

            if ($pending === null || ! self::covers($payment->amount, (float) $pending->amount)) {
                return false;
            }

            $pending->completed_at = now();
            $pending->save();

            return $this->credit($payment, $pending->user_id, $pending->payment_type, $pending->combined_order_id, $pending->package_id);
        });
    }

    /**
     * Run `$credit` in a transaction that is rolled back unless it returns true;
     * a reference recorded twice is a replay and credits nothing.
     *
     * @param  Closure(): bool  $credit
     */
    private function atomically(Closure $credit): bool
    {
        DB::beginTransaction();

        try {
            if ($credit()) {
                DB::commit();

                return true;
            }

            DB::rollBack();

            return false;
        } catch (UniqueConstraintViolationException) {
            DB::rollBack();

            return false;
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    private function credit(VerifiedPayment $payment, int $userId, string $paymentType, ?int $combinedOrderId, ?int $packageId): bool
    {
        return match ($paymentType) {
            self::CART => $combinedOrderId !== null && $this->payOrder($payment, $userId, $combinedOrderId),
            self::WALLET => $this->topUpWallet($payment, $userId),
            self::CUSTOMER_PACKAGE => $packageId !== null && $this->activateCustomerPackage($payment, $userId, $packageId),
            self::SELLER_PACKAGE => $packageId !== null && $this->activateSellerPackage($payment, $userId, $packageId),
            default => false,
        };
    }

    private function payOrder(VerifiedPayment $payment, int $userId, int $combinedOrderId): bool
    {
        $combinedOrder = CombinedOrder::query()->whereKey($combinedOrderId)->where('user_id', $userId)->lockForUpdate()->first();

        if ($combinedOrder === null || ! self::covers($payment->amount, (float) $combinedOrder->grand_total)) {
            return false;
        }

        // The commission reads each order's lines.
        $orders = Order::query()->with('orderDetails')->where('combined_order_id', $combinedOrderId)->lockForUpdate()->get();
        $alreadySettled = $orders->contains(fn (Order $order): bool => $order->payment_status === 'paid' || (int) $order->commission_calculated === 1);

        if ($orders->isEmpty() || $alreadySettled) {
            return false;
        }

        foreach ($orders as $order) {
            $order->payment_status = 'paid';
            $order->payment_details = $payment->details;
            $order->save();

            calculateCommissionAffilationClubPoint($order);
        }

        return true;
    }

    private function topUpWallet(VerifiedPayment $payment, int $userId): bool
    {
        $account = StoreUser::query()->whereKey($userId)->lockForUpdate()->first();

        if ($account === null || $payment->amount <= 0) {
            return false;
        }

        StoreUser::query()->whereKey($account->id)->increment('balance', $payment->amount);

        $wallet = new Wallet;
        $wallet->user_id = $account->id;
        $wallet->amount = $payment->amount;
        $wallet->payment_method = $payment->gateway;
        $wallet->payment_details = $payment->details;
        $wallet->save();

        return true;
    }

    private function activateCustomerPackage(VerifiedPayment $payment, int $userId, int $packageId): bool
    {
        $package = CustomerPackage::query()->find($packageId);
        $account = StoreUser::query()->whereKey($userId)->lockForUpdate()->first();

        if ($package === null || $account === null || ! self::covers($payment->amount, (float) $package->amount)) {
            return false;
        }

        $account->customer_package_id = $package->id;
        $account->remaining_uploads += $package->product_upload;
        $account->save();

        return true;
    }

    private function activateSellerPackage(VerifiedPayment $payment, int $userId, int $packageId): bool
    {
        $package = SellerPackage::query()->find($packageId);

        if ($package === null || ! Shop::query()->where('user_id', $userId)->exists() || ! self::covers($payment->amount, (float) $package->amount)) {
            return false;
        }

        seller_purchase_payment_done($userId, $package->id, $payment->amount, $payment->gateway, $payment->details);

        return true;
    }
}
