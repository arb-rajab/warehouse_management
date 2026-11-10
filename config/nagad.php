<?php

return [
    'sandbox_mode' => env('NAGAD_MODE', 'sandbox'),
    'merchant_id' => env('NAGAD_MERCHANT_ID','683002007104225'),
    'merchant_number' => env('NAGAD_MERCHANT_NUMBER','01979221957'),
    // Unset by default: the storefront sends route('nagad.callback'), which carries the /temp prefix.
    // Set it only to send Nagad somewhere else.
    'callback_url' => env('NAGAD_CALLBACK_URL'),
];
