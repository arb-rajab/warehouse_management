<?php

namespace App\Http\Controllers\Store\Admin\Concerns;

use App\Models\StoreUser;

trait ChecksCustomerAccount
{
    private function assertCustomer(StoreUser $customer): void
    {
        abort_if($customer->user_type !== 'customer' || $customer->hasRole('Super Admin', 'store'), 403);
    }
}
