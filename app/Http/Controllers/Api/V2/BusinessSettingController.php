<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\BusinessSettingCollection;
use App\Models\BusinessSetting;

class BusinessSettingController extends Controller
{
    /**
     * The settings the storefront apps read before sign-in. Everything else in
     * `business_settings` (mail and gateway credentials, API keys) stays out.
     *
     * @var list<string>
     */
    public const PUBLIC_KEYS = [
        'site_name',
        'header_logo',
        'site_icon',
        'system_default_currency',
        'vendor_system_activation',
        'wallet_system',
        'minimum_order_amount',
        'minimum_order_amount_check',
        'cash_payment',
        'pickup_point',
        'shipping_type',
        'verification_form',
        'home_slider_images',
        'home_banner1_images',
        'home_banner2_images',
        'home_banner3_images',
        'home_categories',
    ];

    public function index()
    {
        return new BusinessSettingCollection(BusinessSetting::whereIn('type', self::PUBLIC_KEYS)->get(['type', 'value']));
    }
}
