<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLegacyWebsiteScriptsRequest extends FormRequest
{
    public const KEYS = ['header_script', 'footer_script'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'types' => ['required', 'array', 'size:2'],
            'types.*' => ['required', 'string', 'distinct', Rule::in(self::KEYS)],
            'header_script' => ['present', 'nullable', 'string', 'max:100000'],
            'footer_script' => ['present', 'nullable', 'string', 'max:100000'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), [...self::KEYS, 'types', '_token']) as $key) {
                $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
            }
        }];
    }
}
