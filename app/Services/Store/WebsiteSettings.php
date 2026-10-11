<?php

namespace App\Services\Store;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WebsiteSettings
{
    /**
     * @param  list<string>  $keys
     * @param  list<string>  $arrays
     * @param  list<string>  $localized
     * @param  list<string>  $switches
     * @return array<string, mixed>
     */
    public function read(array $keys, array $arrays = [], array $switches = [], ?string $lang = null, array $localized = []): array
    {
        $values = BusinessSetting::query()->select(['type', 'value'])->whereIn('type', $keys)->addSelect('lang')->get();
        $settings = [];
        foreach ($keys as $key) {
            $rows = $values->where('type', $key);
            $value = (in_array($key, $localized, true) ? ($rows->firstWhere('lang', $lang) ?? $rows->firstWhere('lang', null) ?? $rows->first()) : $rows->firstWhere('lang', null))?->value;
            $settings[$key] = match (true) {
                in_array($key, $switches, true) => $value === 'on',
                in_array($key, $arrays, true) => $this->arrayValues($value),
                default => (string) ($value ?? ''),
            };
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $localized
     * @param  list<string>  $switches
     */
    public function write(array $values, array $switches = [], ?string $lang = null, array $localized = []): void
    {
        DB::transaction(function () use ($values, $switches, $lang, $localized) {
            foreach ($values as $key => $value) {
                $language = in_array($key, $localized, true) ? $lang : null;
                $setting = BusinessSetting::query()->where('type', $key)->where('lang', $language)->first() ?? new BusinessSetting;
                $setting->type = $key;
                $setting->lang = $language;
                $setting->value = match (true) {
                    in_array($key, $switches, true) => $value ? 'on' : null,
                    is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
                    default => $value,
                };
                $setting->save();
            }
        });
        Cache::forget('business_settings');
    }

    /** @return list<string> */
    private function arrayValues(?string $value): array
    {
        $decoded = $value === null ? null : json_decode($value, true);

        return is_array($decoded) ? array_values(array_map(strval(...), array_filter($decoded, fn ($item) => $item === null || is_string($item) || is_int($item)))) : [];
    }
}
