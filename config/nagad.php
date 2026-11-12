<?php

return [
    'sandbox_mode' => env('NAGAD_MODE', 'sandbox'),
    // No defaults: the legacy file shipped a real-looking merchant id and number. Set them here
    // (.env) or on the Payment methods page (App\Support\StorePaymentGateways).
    'merchant_id' => env('NAGAD_MERCHANT_ID'),
    'merchant_number' => env('NAGAD_MERCHANT_NUMBER'),
    // Read with config(), not env(): production runs config:cache, after which env() returns null.
    'pg_public_key' => env('NAGAD_PG_PUBLIC_KEY'),
    'merchant_private_key' => env('NAGAD_MERCHANT_PRIVATE_KEY'),
    // Unset by default: the storefront sends route('nagad.callback'), which carries the /temp prefix.
    // Set it only to send Nagad somewhere else.
    'callback_url' => env('NAGAD_CALLBACK_URL'),
];
