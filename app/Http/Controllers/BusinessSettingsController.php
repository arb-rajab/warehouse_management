<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Artisan;
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
            new Middleware('permission:seller_commission_configuration', only: ['vendor_commission', 'vendor_commission_update']),
            new Middleware('permission:seller_verification_form_configuration', only: ['seller_verification_form', 'seller_verification_form_update']),
            new Middleware('permission:features_activation', only: ['updateSellerCommissionActivation']),
            new Middleware('permission:google_map_setting', only: ['google_map']),
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

    public function google_map(Request $request) {
        return view('shop.backend.setup_configurations.google_configuration.google_map');
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
        flash(translate('These settings are managed in the server environment and cannot be changed here.'))->error();

        return back();
    }

    public function seller_verification_form(Request $request)
    {
        return view('shop.backend.sellers.seller_verification_form.index');
    }

    /**
     * Update sell verification form.
     * @param  Request  $request
     * @return Response
     */
    public function seller_verification_form_update(Request $request)
    {
        $form = [];
        $select_types = ['select', 'multi_select', 'radio'];
        $j = 0;
        for ($i=0; $i < count($request->type); $i++) {
            $item['type'] = $request->type[$i];
            $item['label'] = $request->label[$i];
            if(in_array($request->type[$i], $select_types)){
                $item['options'] = json_encode($request['options_'.$request->option[$j]]);
                $j++;
            }
            array_push($form, $item);
        }
        $business_settings = BusinessSetting::where('type', 'verification_form')->first();
        $business_settings->value = json_encode($form);
        if($business_settings->save()){
            Artisan::call('cache:clear');

            flash(translate("Verification form updated successfully"))->success();
            return back();
        }
    }

    public function vendor_commission(Request $request)
    {
        return view('shop.backend.sellers.seller_commission.index');
    }

    public function vendor_commission_update(Request $request){
        $types = $request->input('types', []);
        abort_unless(is_array($types) && $types !== [] && count(array_filter($types, fn ($type) => is_string($type) && in_array($type, ['vendor_commission', 'minimum_seller_amount_withdraw'], true))) === count($types), 403);
        foreach ($request->types as $key => $type) {
            $business_settings = BusinessSetting::where('type', $type)->first();
            if($business_settings!=null){
                $business_settings->value = $request[$type];
                $business_settings->save();
            }
            else{
                $business_settings = new BusinessSetting;
                $business_settings->type = $type;
                $business_settings->value = $request[$type];
                $business_settings->save();
            }
        }

        Artisan::call('cache:clear');

        flash(translate('Seller Commission updated successfully'))->success();
        return back();
    }

}
