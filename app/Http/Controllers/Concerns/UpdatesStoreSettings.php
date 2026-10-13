<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait UpdatesStoreSettings
{
    /** @param array<string, mixed> $values */
    private function updateSettings(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                $setting = BusinessSetting::query()->where('type', $key)->first() ?? new BusinessSetting;
                $setting->type = $key;
                $setting->value = is_bool($value) ? (int) $value : $value;
                $setting->save();
            }
        });
        Cache::forget('business_settings');
    }
}
