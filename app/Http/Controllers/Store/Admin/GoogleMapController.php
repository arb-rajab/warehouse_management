<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateGoogleMapRequest;
use App\Models\BusinessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GoogleMapController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:google_map_setting')];
    }

    public function update(UpdateGoogleMapRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated() as $type => $value) {
                $setting = BusinessSetting::query()->where('type', $type)->first(['id', 'type', 'value']) ?? new BusinessSetting;
                $setting->type = $type;
                $setting->value = $type === 'google_map' ? ($value ? '1' : '0') : $value;
                $setting->save();
            }
        });

        Cache::forget('business_settings');

        return redirect()->route('google-map.index');
    }
}
