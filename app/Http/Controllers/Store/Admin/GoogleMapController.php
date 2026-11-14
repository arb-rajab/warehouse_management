<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\ReadsHighlight;
use App\Http\Requests\Store\Admin\UpdateGoogleMapRequest;
use App\Models\BusinessSetting;
use App\Support\GoogleMapsKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GoogleMapController extends Controller implements HasMiddleware
{
    use FlashesSuccess;
    use ReadsHighlight;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:google_map_setting')];
    }

    /**
     * The saved key is never sent to the page: only whether one is saved, its
     * masked tail, and whether MAP_API_KEY supplies one.
     */
    public function index(Request $request): Response
    {
        $stored = GoogleMapsKey::stored();

        return Inertia::render('Store/Admin/Setup/GoogleMap/Index', [
            'settings' => [
                'google_map' => (bool) get_setting('google_map', false),
                'google_map_latitude' => (string) get_setting('google_map_latitude', ''),
                'google_map_longtitude' => (string) get_setting('google_map_longtitude', ''),
                'google_map_api_key_set' => $stored !== null,
                'google_map_api_key_mask' => GoogleMapsKey::mask($stored),
                'google_map_api_key_from_env' => GoogleMapsKey::fromEnvironment() !== null,
            ],
            'highlight' => $this->highlight($request, [GoogleMapsKey::SETTING]),
        ]);
    }

    public function update(UpdateGoogleMapRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated() as $type => $value) {
                // A blank key keeps the saved one; a new one replaces it.
                if ($type === GoogleMapsKey::SETTING && blank($value)) {
                    continue;
                }

                $setting = BusinessSetting::query()->where('type', $type)->whereNull('lang')->first(['id', 'type', 'value']) ?? new BusinessSetting;
                $setting->type = $type;
                $setting->value = $type === 'google_map' ? ($value ? '1' : '0') : ($type === GoogleMapsKey::SETTING ? trim($value) : $value);
                $setting->save();
            }
        });

        Cache::forget('business_settings');
        $this->flashSuccess('google_map_settings_have_been_updated_successfully');

        return redirect()->route('google-map.index');
    }
}
