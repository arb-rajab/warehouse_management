<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateShippingConfigurationRequest;
use App\Models\BusinessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ShippingConfigurationController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:shipping_configuration')];
    }

    public function index(): Response
    {
        return Inertia::render('Store/Admin/Shipping/Configuration/Index', [
            'settings' => [
                'shipping_type' => get_setting('shipping_type', 'product_wise_shipping'),
                'flat_rate_shipping_cost' => get_setting('flat_rate_shipping_cost', '0'),
                'shipping_cost_admin' => get_setting('shipping_cost_admin', '0'),
            ],
        ]);
    }

    public function update(UpdateShippingConfigurationRequest $request): RedirectResponse
    {
        $type = $request->validated('type');
        $setting = BusinessSetting::query()->where('type', $type)->first() ?? new BusinessSetting;
        $setting->type = $type;
        $setting->value = (string) $request->validated($type);
        $setting->save();
        Cache::forget('business_settings');

        return redirect()->route('shipping_configuration.index');
    }
}
