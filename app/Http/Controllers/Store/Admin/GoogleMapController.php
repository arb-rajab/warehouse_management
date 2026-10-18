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
use Inertia\Inertia;
use Inertia\Response;

class GoogleMapController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:google_map_setting')];
    }

    public function index(): Response
    {
        return Inertia::render('Store/Admin/Setup/GoogleMap/Index', [
            'settings' => [
                'google_map' => (bool) get_setting('google_map', false),
                'google_map_latitude' => (string) get_setting('google_map_latitude', ''),
                'google_map_longtitude' => (string) get_setting('google_map_longtitude', ''),
            ],
        ]);
    }

    public function update(UpdateGoogleMapRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated() as $type => $value) {
                $setting = BusinessSetting::query()->where('type', $type)->whereNull('lang')->first(['id', 'type', 'value']) ?? new BusinessSetting;
                $setting->type = $type;
                $setting->value = $type === 'google_map' ? ($value ? '1' : '0') : $value;
                $setting->save();
            }
        });

        Cache::forget('business_settings');

        return redirect()->route('google-map.index');
    }
}
