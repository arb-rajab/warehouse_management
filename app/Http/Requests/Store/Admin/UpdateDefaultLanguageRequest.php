<?php

namespace App\Http\Requests\Store\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The default content language chosen on the Languages page
 * (StoreDefaultLanguage). Empty uses the server's DEFAULT_LANGUAGE. A code
 * must name an existing, active language: the check reads the database, so it
 * stays on the server (resources/js/lib/defaultLanguageRules.json holds the
 * rest, kept equal by DefaultLanguageClientValidationParityTest).
 */
class UpdateDefaultLanguageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ['default_language' => ['nullable', 'string', 'max:10']];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $code = $this->input('default_language');

            if ($validator->errors()->has('default_language') || ! is_string($code) || trim($code) === '') {
                return;
            }

            $language = Language::query()->where('code', trim($code))->first(['id', 'code', 'status']);

            if ($language === null) {
                $validator->errors()->add('default_language', __('store_admin.default_language_does_not_exist', ['code' => $code]));
            } elseif ((int) $language->status !== 1) {
                $validator->errors()->add('default_language', __('store_admin.default_language_is_switched_off', ['code' => $code]));
            }
        }];
    }
}
