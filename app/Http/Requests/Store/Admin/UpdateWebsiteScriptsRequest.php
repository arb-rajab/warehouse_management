<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWebsiteScriptsRequest extends FormRequest
{
    use ValidatesWebsiteSettings;

    public const KEYS = ['header_script', 'footer_script'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:'.implode(',', self::KEYS)],
            'settings.header_script' => ['present', 'nullable', 'string', 'max:100000'],
            'settings.footer_script' => ['present', 'nullable', 'string', 'max:100000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->refuseUnexpectedSettingInput($validator, ['settings']);
        });
    }
}
