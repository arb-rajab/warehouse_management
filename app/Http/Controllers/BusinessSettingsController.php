<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Http\Requests\Store\Admin\UpdateWebsiteHeaderRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteHomeRequest;
use Illuminate\Support\Arr;
use App\Models\Product;
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
            new Middleware('permission:general_settings', only: ['general_setting']),
            new Middleware('permission:features_activation', only: ['activation']),
            new Middleware('permission:smtp_settings', only: ['smtp_settings']),
            new Middleware('permission:payment_methods_configurations', only: ['payment_method', 'payment_method_update']),
            new Middleware('permission:order_configuration', only: ['order_configuration']),
            new Middleware('permission:file_system_&_cache_configuration', only: ['file_system']),
            new Middleware('permission:social_media_logins', only: ['social_login']),
            new Middleware('permission:facebook_chat', only: ['facebook_chat', 'facebook_chat_update']),
            new Middleware('permission:facebook_comment', only: ['facebook_comment', 'facebook_comment_update']),
            new Middleware('permission:analytics_tools_configuration', only: ['google_analytics', 'google_analytics_update', 'facebook_pixel_update']),
            new Middleware('permission:google_recaptcha_configuration', only: ['google_recaptcha', 'google_recaptcha_update']),
            new Middleware('permission:google_map_setting', only: ['google_map', 'google_map_update']),
            new Middleware('permission:google_firebase_setting', only: ['google_firebase', 'google_firebase_update']),
            new Middleware('permission:shipping_configuration', only: ['shipping_configuration', 'shipping_configuration_update']),
            // Only the remaining general/order/map/currency pages use this legacy writer.
            new Middleware('permission:general_settings|order_configuration|google_map_setting|currency_setup', only: ['update']),
        ];
    }
    public function general_setting(Request $request)
    {
    	return view('shop.backend.setup_configurations.general_settings');
    }

    public function activation(Request $request)
    {
    	return view('shop.backend.setup_configurations.activation');
    }

    public function social_login(Request $request)
    {
        return view('shop.backend.setup_configurations.social_login');
    }

    public function smtp_settings(Request $request)
    {
        return view('shop.backend.setup_configurations.smtp_settings');
    }

    public function google_analytics(Request $request)
    {
        return view('shop.backend.setup_configurations.google_configuration.google_analytics');
    }

    public function google_recaptcha(Request $request)
    {
        return view('shop.backend.setup_configurations.google_configuration.google_recaptcha');
    }

    public function google_map(Request $request) {
        return view('shop.backend.setup_configurations.google_configuration.google_map');
    }

    public function google_firebase(Request $request) {
        return view('shop.backend.setup_configurations.google_configuration.google_firebase');
    }

    public function facebook_chat(Request $request)
    {
        return view('shop.backend.setup_configurations.facebook_chat');
    }

    public function facebook_comment(Request $request)
    {
        return view('shop.backend.setup_configurations.facebook_configuration.facebook_comment');
    }

    public function payment_method(Request $request)
    {
        return view('shop.backend.setup_configurations.payment_method');
    }

    public function file_system(Request $request)
    {
        return view('shop.backend.setup_configurations.file_system');
    }

    /**
     * Update the API key's for payment methods.
     * @param  Request  $request
     * @return Response
     */
    public function payment_method_update(Request $request)
    {
        foreach ($request->types as $key => $type) {
                $this->overWriteEnvFile($type, $request[$type]);
        }

        $business_settings = BusinessSetting::where('type', $request->payment_method.'_sandbox')->first();
        if($business_settings != null){
            if ($request->has($request->payment_method.'_sandbox')) {
                $business_settings->value = 1;
                $business_settings->save();
            }
            else{
                $business_settings->value = 0;
                $business_settings->save();
            }
        }

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
    }

    /**
     * Update the API key's for GOOGLE analytics.
     * @param  Request  $request
     * @return Response
     */
    public function google_analytics_update(Request $request)
    {
        foreach ($request->types as $key => $type) {
                $this->overWriteEnvFile($type, $request[$type]);
        }

        $business_settings = BusinessSetting::where('type', 'google_analytics')->first();

        if ($request->has('google_analytics')) {
            $business_settings->value = 1;
            $business_settings->save();
        }
        else{
            $business_settings->value = 0;
            $business_settings->save();
        }

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
    }

    public function google_recaptcha_update(Request $request)
    {
        foreach ($request->types as $key => $type) {
            $this->overWriteEnvFile($type, $request[$type]);
        }

        $business_settings = BusinessSetting::where('type', 'google_recaptcha')->first();

        if ($request->has('google_recaptcha')) {
            $business_settings->value = 1;
            $business_settings->save();
        }
        else{
            $business_settings->value = 0;
            $business_settings->save();
        }

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
    }

    public function google_map_update(Request $request)
    {
        foreach ($request->types as $key => $type) {
            $this->overWriteEnvFile($type, $request[$type]);
        }

        $business_settings = BusinessSetting::where('type', 'google_map')->first();

        if ($request->has('google_map')) {
            $business_settings->value = 1;
            $business_settings->save();
        }
        else{
            $business_settings->value = 0;
            $business_settings->save();
        }

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
    }

    public function google_firebase_update(Request $request)
    {
        foreach ($request->types as $key => $type) {
            $this->overWriteEnvFile($type, $request[$type]);
        }

        $business_settings = BusinessSetting::where('type', 'google_firebase')->first();

        if ($request->has('google_firebase')) {
            $business_settings->value = 1;
            $business_settings->save();
        }
        else{
            $business_settings->value = 0;
            $business_settings->save();
        }

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
    }


    /**
     * Update the API key's for GOOGLE analytics.
     * @param  Request  $request
     * @return Response
     */
    public function facebook_chat_update(Request $request)
    {
        foreach ($request->types as $key => $type) {
                $this->overWriteEnvFile($type, $request[$type]);
        }

        $business_settings = BusinessSetting::where('type', 'facebook_chat')->first();

        if ($request->has('facebook_chat')) {
            $business_settings->value = 1;
            $business_settings->save();
        }
        else{
            $business_settings->value = 0;
            $business_settings->save();
        }

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
    }

    public function facebook_comment_update(Request $request)
    {
        foreach ($request->types as $key => $type) {
            $this->overWriteEnvFile($type, $request[$type]);
        }

        $business_settings = BusinessSetting::where('type', 'facebook_comment')->first();
        if(!$business_settings) {
            $business_settings = new BusinessSetting;
            $business_settings->type = 'facebook_comment';
        }

        $business_settings->value = 0;
        if ($request->facebook_comment) {
            $business_settings->value = 1;
        }

        $business_settings->save();

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
    }

    public function facebook_pixel_update(Request $request)
    {
        foreach ($request->types as $key => $type) {
                $this->overWriteEnvFile($type, $request[$type]);
        }

        $business_settings = BusinessSetting::where('type', 'facebook_pixel')->first();

        if ($request->has('facebook_pixel')) {
            $business_settings->value = 1;
            $business_settings->save();
        }
        else{
            $business_settings->value = 0;
            $business_settings->save();
        }

        Artisan::call('cache:clear');

        flash(translate("Settings updated successfully"))->success();
        return back();
    }

    /**
     * Update the API key's for other methods.
     * @param  Request  $request
     * @return Response
     */
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

    private function refuseWebsiteSettingKeys(array $types): void
    {
        // Migrated keys belong to their exact page endpoint. Script writes remain
        // refused until the owner defines their permission policy.
        foreach (Arr::flatten($types) as $type) {
            abort_if(in_array($type, [...UpdateWebsiteHeaderRequest::KEYS, ...UpdateWebsiteHomeRequest::KEYS, ...\App\Http\Requests\Store\Admin\UpdateWebsiteFooterRequest::KEYS, ...\App\Http\Requests\Store\Admin\UpdateWebsiteAppearanceRequest::KEYS, 'header_script', 'footer_script'], true), 403);
        }
    }

    public function update(Request $request)
    {
        $this->refuseWebsiteSettingKeys((array) $request->input('types', []));

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

    public function updateActivationSettings(Request $request)
    {
        $this->refuseWebsiteSettingKeys((array) $request->input('type', []));
        $env_changes = ['FORCE_HTTPS', 'FILESYSTEM_DRIVER'];
        if (in_array($request->type, $env_changes)) {

            return $this->updateActivationSettingsInEnv($request);
        }

        $business_settings = BusinessSetting::where('type', $request->type)->first();
        if($business_settings!=null){
            if ($request->type == 'maintenance_mode' && $request->value == '1') {
                if(env('DEMO_MODE') != 'On'){
                    Artisan::call('down');
                }
            }
            elseif ($request->type == 'maintenance_mode' && $request->value == '0') {
                if(env('DEMO_MODE') != 'On') {
                    Artisan::call('up');
                }
            }
            $business_settings->value = $request->value;
            $business_settings->save();
        }
        else{
            $business_settings = new BusinessSetting;
            $business_settings->type = $request->type;
            $business_settings->value = $request->value;
            $business_settings->save();
        }

        Artisan::call('cache:clear');
        return '1';
    }

    /**
     * `FORCE_HTTPS` and `FILESYSTEM_DRIVER` live in `.env`, which the panel no
     * longer writes. '0' is what the toggle's script reads as a failure.
     */
    public function updateActivationSettingsInEnv($request)
    {
        $this->overWriteEnvFile($request->type, $request->value);

        return '0';
    }

    public function vendor_commission(Request $request)
    {
        return view('shop.backend.sellers.seller_commission.index');
    }

    public function vendor_commission_update(Request $request){
        $this->refuseWebsiteSettingKeys((array) $request->input('types', []));
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

    public function shipping_configuration(Request $request){
        return view('shop.backend.setup_configurations.shipping_configuration.index');
    }

    public function shipping_configuration_update(Request $request){
        $this->refuseWebsiteSettingKeys((array) $request->input('type', []));
        $business_settings = BusinessSetting::where('type', $request->type)->first();
        $business_settings->value = $request[$request->type];
        $business_settings->save();

        Artisan::call('cache:clear');
        flash(translate('Shipping Method updated successfully'))->success();
        return back();
    }

    public function order_configuration(){
        return view('shop.backend.setup_configurations.order_configuration.index');
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        $results = Product::where('mat_id', 'like', '%' . $query . '%')
            ->select('id', 'name', 'mat_id')
            ->get();

        return response()->json($results);
    }
}
