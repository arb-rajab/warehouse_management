<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreUploadReference;
use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGeneralSettingsRequest extends FormRequest
{
    use ValidatesStoreUploadReference;
    use ValidatesWebsiteSettings;

    /** The upload ids the form stores, as the legacy uploader stored them. */
    public const IMAGES = ['system_logo_white', 'system_logo_black', 'admin_login_background'];

    /**
     * Every key the form posts. `timezone` is validated but never stored: it
     * comes from config('app.timezone'), and the legacy writer only ever
     * handed it to the refusing `.env` writer.
     */
    public const KEYS = ['site_name', 'timezone', ...self::IMAGES];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'settings' => ['required', 'array:'.implode(',', self::KEYS)],
            'settings.site_name' => ['present', 'nullable', 'string', 'max:255'],
            'settings.timezone' => ['required', 'string', Rule::in(array_keys(timezones()))],
        ];
        foreach (self::IMAGES as $key) {
            $rules['settings.'.$key] = ['present', 'nullable', 'integer', $this->retainableImageUploadRule('settings.'.$key, $this->storedSettingValue($key))];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->refuseUnexpectedSettingInput($validator);
            $timezone = $this->input('settings.timezone');
            if (! $validator->errors()->has('settings.timezone') && $timezone !== app_timezone()) {
                $validator->errors()->add('settings.timezone', __('messages.store_timezone_environment_managed'));
            }
        });
    }
}
