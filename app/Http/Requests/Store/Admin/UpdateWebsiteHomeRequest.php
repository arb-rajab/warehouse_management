<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWebsiteHomeRequest extends FormRequest
{
    use ValidatesWebsiteSettings;

    public const KEYS = ['home_slider_images', 'home_slider_links', 'cupon_background_color', 'cupon_title', 'cupon_subtitle', 'home_categories'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:'.implode(',', self::KEYS)],
            'settings.home_slider_images' => ['present', 'array', 'list', 'max:50'],
            'settings.home_slider_images.*' => $this->imageSettingRules(),
            'settings.home_slider_links' => ['present', 'array', 'list', 'max:50'],
            'settings.home_slider_links.*' => $this->linkSettingRules(),
            'settings.cupon_background_color' => ['present', 'nullable', 'string', 'regex:/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i'],
            'settings.cupon_title' => ['present', 'nullable', 'string', 'max:255'],
            'settings.cupon_subtitle' => ['present', 'nullable', 'string', 'max:255'],
            'settings.home_categories' => ['present', 'array', 'list', 'max:50'],
            'settings.home_categories.*' => ['required', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->refuseUnexpectedSettingInput($validator);
            $this->validatePairedSettings($validator, 'home_slider_images', 'home_slider_links');
        });
    }
}
