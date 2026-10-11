<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWebsiteFooterRequest extends FormRequest
{
    use ValidatesWebsiteSettings;

    public const KEYS = ['footer_logo', 'about_us_description', 'play_store_link', 'app_store_link', 'contact_address', 'contact_phone', 'contact_email', 'widget_one', 'widget_one_labels', 'widget_one_links', 'frontend_copyright_text', 'show_social_links', 'facebook_link', 'twitter_link', 'instagram_link', 'youtube_link', 'linkedin_link', 'seller_app_link', 'payment_method_images', 'registration_tutorial_button', 'registration_tutorial_text', 'registration_tutorial_link'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:'.implode(',', self::KEYS)],
            'settings.footer_logo' => ['present', ...$this->imageSettingRules()],
            'settings.about_us_description' => ['present', 'nullable', 'string', 'max:100000'],
            'settings.play_store_link' => ['present', ...$this->linkSettingRules()],
            'settings.app_store_link' => ['present', ...$this->linkSettingRules()],
            'settings.contact_address' => ['present', 'nullable', 'string', 'max:255'],
            'settings.contact_phone' => ['present', 'nullable', 'string', 'max:255'],
            'settings.contact_email' => ['present', 'nullable', 'email', 'max:255'],
            'settings.widget_one' => ['present', 'nullable', 'string', 'max:255'],
            'settings.widget_one_labels' => ['present', 'array', 'list', 'max:50'],
            'settings.widget_one_links' => ['present', 'array', 'list', 'max:50'],
            'settings.frontend_copyright_text' => ['present', 'nullable', 'string', 'max:100000'],
            'settings.show_social_links' => ['required', 'boolean'],
            'settings.facebook_link' => ['present', ...$this->linkSettingRules()],
            'settings.twitter_link' => ['present', ...$this->linkSettingRules()],
            'settings.instagram_link' => ['present', ...$this->linkSettingRules()],
            'settings.youtube_link' => ['present', ...$this->linkSettingRules()],
            'settings.linkedin_link' => ['present', ...$this->linkSettingRules()],
            'settings.seller_app_link' => ['present', ...$this->linkSettingRules()],
            'settings.payment_method_images' => ['present', ...$this->uploadIdsRules('image')],
            'settings.registration_tutorial_button' => ['required', 'boolean'],
            'settings.registration_tutorial_text' => ['present', 'nullable', 'string', 'max:255'],
            'settings.registration_tutorial_link' => ['present', ...$this->linkSettingRules()],
            'lang' => ['required', 'string', Rule::in([config('app.default_language'), ...Language::query()->pluck('code')])],
            'settings.widget_one_labels.*' => ['required', 'string', 'max:256'],
            'settings.widget_one_links.*' => ['required', ...$this->linkSettingRules()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->refuseUnexpectedSettingInput($validator, ['settings', 'lang']);
            $this->validatePairedSettings($validator, 'widget_one_labels', 'widget_one_links');
        });
    }
}
