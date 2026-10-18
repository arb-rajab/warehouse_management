<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string|null $name
 * @property-read int $verification_status
 * @property-read string|null $verification_info
 * @property-read float $admin_to_pay
 * @property-read StoreUser $user
 */
class SellerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'account_name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'banned' => (bool) $this->user->banned,
            'approved' => (bool) $this->verification_status,
            'has_verification' => $this->verification_info !== null,
            'products_count' => (int) $this->user->getAttribute('products_count'),
            'due' => get_setting('Payouts_Section') == 1 ? single_price(abs($this->admin_to_pay)) : null,
            'due_to_admin' => $this->admin_to_pay < 0,
            'payment_history_url' => $request->user('store')?->can('seller_payment_history')
                ? route('sellers.payment_history', encrypt($this->user_id)) : null,
        ];
    }
}
