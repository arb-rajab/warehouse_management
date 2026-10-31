<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BusinessSettingsController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:features_activation', only: ['updateSellerCommissionActivation']),
        ];
    }
    public function updateSellerCommissionActivation(\App\Http\Requests\Store\Admin\UpdateSellerCommissionActivationRequest $request): Response
    {
        $setting = BusinessSetting::query()->where('type', $request->validated('type'))->whereNull('lang')->first() ?? new BusinessSetting;
        $setting->type = $request->validated('type');
        $setting->value = $request->boolean('value') ? '1' : '0';
        $setting->save();
        \Illuminate\Support\Facades\Cache::forget('business_settings');

        return response('1');
    }

    /**
     * The settings screens used to post `.env` keys here. The server's `.env` is
     * managed by the deploy, so nothing in the admin panel writes it any more.
     *
     * @param  Request  $request
     * @return Response
     */
    public function env_key_update(Request $request)
    {
        flash(__('store_admin.these_settings_are_managed_in_the_server_environment_and_cannot_be_changed_here'))->error();

        return back();
    }

}
