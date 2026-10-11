<?php

namespace App\Http\Requests\Concerns;

use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesStoreLanguage
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function languageRules(string $presence): array
    {
        return ['lang' => [$presence, 'string', 'max:10', Rule::in([config('app.default_language'), ...Language::query()->pluck('code')])]];
    }
}
