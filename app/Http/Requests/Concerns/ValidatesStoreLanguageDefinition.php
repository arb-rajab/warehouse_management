<?php

namespace App\Http\Requests\Concerns;

use App\Models\Language;
use Illuminate\Validation\Rule;

trait ValidatesStoreLanguageDefinition
{
    /** @return array<string, mixed> */
    protected function languageDefinitionRules(?Language $language = null): array
    {
        $codeRules = ['required', 'string', 'max:10', 'regex:/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,4})?$/', Rule::unique('languages', 'code')->ignore($language?->id)];
        if ($language !== null && in_array($language->code, ['en', config('app.default_language')], true)) {
            $codeRules[] = Rule::in([$language->code]);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => $codeRules,
            'app_lang_code' => ['required', 'string', 'max:10', 'regex:/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,4})?$/'],
        ];
    }
}
