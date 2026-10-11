<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWebsiteAppearanceRequest extends FormRequest
{
    use ValidatesWebsiteSettings;

    public const KEYS = ['website_name', 'site_motto', 'site_icon', 'base_color', 'base_hov_color', 'login_page_image', 'register_page_image', 'seller_login_page_bg', 'flash_deal_banner', 'flash_deal_banner_small', 'meta_title', 'meta_description', 'meta_keywords', 'meta_image', 'cookies_agreement_text', 'show_cookies_agreement', 'show_website_popup', 'website_popup_content', 'show_subscribe_form'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:'.implode(',', self::KEYS)],
            'settings.website_name' => ['present', 'nullable', 'string', 'max:255'],
            'settings.site_motto' => ['present', 'nullable', 'string', 'max:255'],
            'settings.site_icon' => ['present', ...$this->imageSettingRules()],
            'settings.base_color' => ['present', 'nullable', 'string', 'regex:/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i'],
            'settings.base_hov_color' => ['present', 'nullable', 'string', 'regex:/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i'],
            'settings.login_page_image' => ['present', ...$this->imageSettingRules()],
            'settings.register_page_image' => ['present', ...$this->imageSettingRules()],
            'settings.seller_login_page_bg' => ['present', ...$this->imageSettingRules()],
            'settings.flash_deal_banner' => ['present', ...$this->imageSettingRules()],
            'settings.flash_deal_banner_small' => ['present', ...$this->imageSettingRules()],
            'settings.meta_title' => ['present', 'nullable', 'string', 'max:255'],
            'settings.meta_description' => ['present', 'nullable', 'string', 'max:2000'],
            'settings.meta_keywords' => ['present', 'nullable', 'string', 'max:2000'],
            'settings.meta_image' => ['present', ...$this->imageSettingRules()],
            'settings.cookies_agreement_text' => ['present', 'nullable', 'string', 'max:100000'],
            'settings.show_cookies_agreement' => ['required', 'boolean'],
            'settings.show_website_popup' => ['required', 'boolean'],
            'settings.website_popup_content' => ['present', 'nullable', 'string', 'max:100000'],
            'settings.show_subscribe_form' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->refuseUnexpectedSettingInput($validator, ['settings']);
        });
    }
}
