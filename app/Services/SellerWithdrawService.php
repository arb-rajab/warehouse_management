<?php

namespace App\Services;

use App\Models\SellerWithdrawRequest;
use App\Models\Shop;
use App\Models\StoreUser;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Opens a seller's withdraw request against the balance their shop is owed.
 *
 * The web panel took any amount at all and the mobile API checked it against
 * the balance with no lock and no regard for requests still waiting, so a
 * seller could stack requests (or race two at once) until they asked for
 * several times what they were owed. The shop row is locked, the amount is
 * checked against the balance minus the requests still pending, and the
 * request is written in the same transaction.
 */
class SellerWithdrawService
{
    /**
     * @throws DomainException with the message to show the seller when the request is refused
     */
    public function request(StoreUser $seller, float $amount, ?string $message): SellerWithdrawRequest
    {
        return DB::transaction(function () use ($seller, $amount, $message): SellerWithdrawRequest {
            $shop = Shop::query()->where('user_id', $seller->id)->lockForUpdate()->first();

            if ($shop === null || $shop->admin_to_pay <= 5) {
                throw new DomainException(translate('You do not have enough balance to send withdraw request'));
            }

            $pending = (float) SellerWithdrawRequest::query()
                ->where('user_id', $seller->id)
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 0))
                ->sum('amount');

            if ($amount < (float) get_setting('minimum_seller_amount_withdraw') || $amount > (float) $shop->admin_to_pay - $pending) {
                throw new DomainException(translate('Invalid amount'));
            }

            $withdrawRequest = new SellerWithdrawRequest;
            $withdrawRequest->user_id = $seller->id;
            $withdrawRequest->amount = $amount;
            $withdrawRequest->message = $message;
            $withdrawRequest->status = 0;
            $withdrawRequest->viewed = 0;
            $withdrawRequest->save();

            return $withdrawRequest;
        });
    }
}
