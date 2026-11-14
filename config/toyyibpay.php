<?php

// ToyyibPay has no payment flow in the store; its keys can be stored on the Payment methods page
// (App\Support\StorePaymentGateways), which falls back to these.
return [
    'key' => env('TOYYIBPAY_KEY', ''),
    'category' => env('TOYYIBPAY_CATEGORY', ''),
    // `sandbox` (dev.toyyibpay.com) or `live`.
    'mode' => env('TOYYIBPAY_MODE', 'sandbox'),
];
