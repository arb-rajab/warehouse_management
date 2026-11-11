<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Controllers\Store\Admin\SettingsController;
use App\Support\StoreIntegrationIds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Analytics, Facebook chat and Facebook comments settings pages: the page's
 * on/off switches (`settings[...]`) and the public ID each one prints
 * (StoreIntegrationIds). The page is the route's action, as in
 * UpdateStoreSettingsRequest. Every ID is optional: an empty one falls back to
 * the server's `.env` value, and a switch can be on while the `.env` holds the ID.
 *
 * An ID lands in `<script>` blocks and URLs, so it may hold only the characters
 * real IDs use: no quote, angle bracket, space or line break. The browser
 * checks a copy of these rules (resources/js/lib/integrationIdsRules.json, kept
 * equal by IntegrationIdsClientValidationParityTest).
 */
class UpdateIntegrationIdsRequest extends FormRequest
{
    /**
     * The rules of one page.
     *
     * @return array<string, array<mixed>>
     */
    public static function rulesFor(string $page): array
    {
        $config = SettingsController::PAGES[$page];
        $rules = [
            'settings' => ['required', 'array:'.implode(',', $config['keys']), 'min:1'],
            'settings.*' => ['required', 'boolean'],
        ];

        foreach (array_values($config['ids']) as $field) {
            $rules[$field] = ['nullable', 'string', 'max:40', 'regex:'.StoreIntegrationIds::PATTERNS[$field]];
        }

        return $rules;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return self::rulesFor(lcfirst(substr($this->route()->getActionMethod(), strlen('update'))));
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $page = lcfirst(substr($this->route()->getActionMethod(), strlen('update')));
            $allowed = ['settings', '_token', '_method', ...array_values(SettingsController::PAGES[$page]['ids'])];

            if (array_diff(array_keys($this->all()), $allowed) !== []) {
                $validator->errors()->add('settings', __('validation.prohibited', ['attribute' => 'settings']));
            }
        }];
    }
}
