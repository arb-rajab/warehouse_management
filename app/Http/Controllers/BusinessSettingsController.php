<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Artisan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;

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
            new Middleware('permission:general_settings|footer_setup|website_appearance|edit_website_page', only: ['update']),
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

    /**
     * Refuses to edit the `.env` file; kept so the settings screens that call
     * it still run.
     *
     * It took the key and the value straight from the request, with no list of
     * keys, no escaping and no permission check on most routes that reach it,
     * so any admin, staff or accountant account could rewrite `APP_KEY`, the
     * database credentials or `WMS_TOKEN`. Its `DEMO_MODE` guard read env(),
     * which is null once config is cached, so it never held on a deployed
     * host. The file is shared between releases and managed by the deploy, and
     * a changed value is ignored under `config:cache` anyway.
     *
     * Only the key is logged, never the value: it may be a credential.
     *
     * @param  mixed  $type
     * @param  mixed  $val
     * @return bool Always false: nothing was written.
     */
    public function overWriteEnvFile($type, $val)
    {
        Log::warning('Refused to write the .env file from the admin panel.', [
            'key' => is_scalar($type) ? (string) $type : gettype($type),
            'user_id' => auth()->id(),
        ]);

        if (! session()->has('env_write_refused')) {
            session()->flash('env_write_refused', true);
            flash(translate('Environment settings are managed on the server and were not saved.'))->warning();
        }

        return false;
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

    public function update(Request $request)
    {
        $migratedKeys = array_merge(...array_column(\App\Http\Controllers\Store\Admin\SettingsController::PAGES, 'keys'));
        $migratedKeys = array_merge($migratedKeys, \App\Http\Requests\Store\Admin\UpdateWebsiteHeaderRequest::KEYS, \App\Http\Requests\Store\Admin\UpdateWebsiteHomeRequest::KEYS, \App\Http\Requests\Store\Admin\UpdateGeneralSettingsRequest::KEYS, ['header_script', 'footer_script', 'google_map', 'google_map_latitude', 'google_map_longtitude', 'MAP_API_KEY']);
        $types = $request->input('types', []);
        abort_unless(is_array($types), 422);
        $postedKeys = [];
        array_walk_recursive($types, function ($key) use (&$postedKeys) { $postedKeys[] = $key; });
        abort_if(array_intersect($postedKeys, [...$migratedKeys, 'shipping_type', 'flat_rate_shipping_cost', 'shipping_cost_admin', 'minimum_order_amount_check', 'minimum_order_amount', 'default_specific_product', 'default_representative_check', 'default_representative_id', 'system_default_currency', 'symbol_format', 'decimal_separator', 'no_of_decimals', 'maintenance_mode', 'FORCE_HTTPS', 'FILESYSTEM_DRIVER']) !== [], 403);

        foreach ($request->types as $key => $type) {
            if($type == 'site_name'){
                $this->overWriteEnvFile('APP_NAME', $request[$type]);
            }
            if($type == 'timezone'){
                $this->overWriteEnvFile('APP_TIMEZONE', $request[$type]);
            }
            else {
                $lang = null;
                if(gettype($type) == 'array'){
                    $lang = array_key_first($type);
                    $type = $type[$lang];
                    $business_settings = BusinessSetting::where('type', $type)->where('lang',$lang)->first();
                }else{
                    $business_settings = BusinessSetting::where('type', $type)->first();
                }

                if($business_settings!=null){
                    if(gettype($request[$type]) == 'array'){
                        $business_settings->value = json_encode($request[$type]);
                    }
                    else {
                        $business_settings->value = $request[$type];
                    }
                    $business_settings->lang = $lang;
                    $business_settings->save();
                }
                else{
                    $business_settings = new BusinessSetting;
                    $business_settings->type = $type;
                    if(gettype($request[$type]) == 'array'){
                        $business_settings->value = json_encode($request[$type]);
                    }
                    else {
                        $business_settings->value = $request[$type];
                    }
                    $business_settings->lang = $lang;
                    $business_settings->save();
                }
            }
        }

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
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
