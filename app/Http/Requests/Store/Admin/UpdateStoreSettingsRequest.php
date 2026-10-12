<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Controllers\Store\Admin\SettingsController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStoreSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $page = lcfirst(substr($this->route()->getActionMethod(), strlen('update')));
        $keys = SettingsController::PAGES[$page]['keys'];

        return [
            'settings' => ['required', 'array:'.implode(',', $keys), 'min:1'],
            'settings.*' => ['required', 'boolean'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['settings', '_token', '_method']) !== []) {
                $validator->errors()->add('settings', __('validation.prohibited', ['attribute' => 'settings']));
            }
        }];
    }
}
