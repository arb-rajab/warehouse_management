<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

class ApiResourceLabel
{
    /** Unknown statuses keep the display value previously returned to mobile clients. */
    public static function status(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        return Lang::has('storefront.status.'.$status)
            ? StoreLocale::status($status)
            : ucwords(str_replace('_', ' ', (string) $status));
    }

    public static function deliveryStatus(?string $status): string
    {
        return $status === 'pending' ? __('api_resources.order.placed') : self::status($status);
    }
}
