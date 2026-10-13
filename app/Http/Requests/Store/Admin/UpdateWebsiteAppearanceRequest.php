<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreUploadReference;
use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The appearance form's typed writer: the keys the retired Blade appearance
 * form posted to the generic settings writer, and nothing else. The header
 * and footer scripts keep their own compatibility endpoint
 * (UpdateLegacyWebsiteScriptsRequest) and are refused here
 * (.ai/rules/store-backoffice-migration.md §20).
 */
class UpdateWebsiteAppearanceRequest extends FormRequest
{
    use ValidatesStoreUploadReference;
    use ValidatesWebsiteSettings;

    /** Upload ids, stored as strings as the legacy uploader stored them. */
    public const IMAGES = ['site_icon', 'login_page_image', 'register_page_image', 'seller_login_page_bg', 'delivery_boy_login_page_bg', 'flash_deal_banner', 'flash_deal_banner_small', 'meta_image'];

    /** Edited with the shared editor and cleaned with HtmlSanitizer before validation. */
    public const RICH_TEXT = ['cookies_agreement_text', 'website_popup_content'];

    /** Stored as `on` or null, as the Blade checkboxes were. */
    public const SWITCHES = ['show_cookies_agreement', 'show_website_popup', 'show_subscribe_form'];

    /**
     * Every key the form can write. `seller_login_page_bg` is shown only while
     * the vendor system is on and `delivery_boy_login_page_bg` only while the
     * delivery boy addon is (enabledKeys()).
     */
    public const KEYS = ['website_name', 'site_motto', 'base_color', 'base_hov_color', 'meta_title', 'meta_description', 'meta_keywords', ...self::IMAGES, ...self::RICH_TEXT, ...self::SWITCHES];

    /** @return list<string> */
    public static function enabledKeys(): array
    {
        return self::enabledSettingKeys(self::KEYS, 'seller_login_page_bg', 'delivery_boy_login_page_bg');
    }

    protected function prepareForValidation(): void
    {
        $this->cleanRichTextSettings(self::RICH_TEXT);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $keys = self::enabledKeys();
        $color = ['present', 'nullable', 'string', 'regex:/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i'];
        $rules = [
            'settings' => ['required', 'array:'.implode(',', $keys)],
            'settings.website_name' => ['present', 'nullable', 'string', 'max:255'],
            'settings.site_motto' => ['present', 'nullable', 'string', 'max:255'],
            'settings.base_color' => $color,
            'settings.base_hov_color' => $color,
            'settings.meta_title' => ['present', 'nullable', 'string', 'max:255'],
            'settings.meta_description' => ['present', 'nullable', 'string', 'max:65535'],
            'settings.meta_keywords' => ['present', 'nullable', 'string', 'max:65535'],
        ];
        foreach (array_intersect(self::IMAGES, $keys) as $key) {
            $rules['settings.'.$key] = ['present', 'nullable', 'integer', $this->retainableImageUploadRule('settings.'.$key, $this->storedSettingValue($key))];
        }
        foreach (self::RICH_TEXT as $key) {
            $rules['settings.'.$key] = ['present', 'nullable', 'string'];
        }
        foreach (self::SWITCHES as $key) {
            $rules['settings.'.$key] = ['required', 'boolean'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->refuseUnexpectedSettingInput($validator);
        });
    }
}
