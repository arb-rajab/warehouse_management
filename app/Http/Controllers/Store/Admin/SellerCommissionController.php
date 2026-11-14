<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\UpdatesStoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Requests\Store\Admin\UpdateSellerCommissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SellerCommissionController extends Controller implements HasMiddleware
{
    use FlashesSuccess;
    use UpdatesStoreSettings;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:seller_commission_configuration')];
    }

    public function index(): Response
    {
        return Inertia::render('Store/Admin/Sellers/Commission/Index', [
            'settings' => [
                'vendor_commission' => (string) get_setting('vendor_commission', ''),
                'minimum_seller_amount_withdraw' => (string) get_setting('minimum_seller_amount_withdraw', ''),
                'vendor_commission_activation' => get_setting('vendor_commission_activation') == 1,
                'category_wise_commission' => get_setting('category_wise_commission') == 1,
            ],
        ]);
    }

    public function update(UpdateSellerCommissionRequest $request): RedirectResponse
    {
        $this->updateSettings($request->settings());
        $this->flashSuccess('seller_commission_has_been_updated_successfully');

        return redirect()->route('business_settings.vendor_commission');
    }
}
