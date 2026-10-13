<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWebsiteHeaderRequest extends FormRequest
{
    use ValidatesWebsiteSettings;

    public const KEYS = ['header_logo', 'show_language_switcher', 'show_currency_switcher', 'header_stikcy', 'topbar_banner', 'topbar_banner_medium', 'topbar_banner_small', 'topbar_banner_link', 'helpline_number', 'header_menu_labels', 'header_menu_links'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $image = ['present', ...$this->imageSettingRules()];
        $link = $this->linkSettingRules();

        return [
            'settings' => ['required', 'array:'.implode(',', self::KEYS)],
            'settings.header_logo' => $image,
            'settings.topbar_banner' => $image,
            'settings.topbar_banner_medium' => $image,
            'settings.topbar_banner_small' => $image,
            'settings.show_language_switcher' => ['required', 'boolean'],
            'settings.show_currency_switcher' => ['required', 'boolean'],
            'settings.header_stikcy' => ['required', 'boolean'],
            'settings.topbar_banner_link' => ['present', ...$link],
            'settings.helpline_number' => ['present', 'nullable', 'string', 'max:100'],
            'settings.header_menu_labels' => ['present', 'array', 'list', 'max:50'],
            'settings.header_menu_labels.*' => ['required', 'string', 'max:256'],
            'settings.header_menu_links' => ['present', 'array', 'list', 'max:50'],
            'settings.header_menu_links.*' => ['required', ...$link],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->refuseUnexpectedSettingInput($validator);
            $this->validatePairedSettings($validator, 'header_menu_labels', 'header_menu_links');
        });
    }
}
