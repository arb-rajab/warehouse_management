<?php

namespace App\Http\Requests\Concerns;

use App\Models\Attribute;
use App\Models\Color;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesStoreCatalogue
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function attributeNameRules(): array
    {
        return ['name' => ['required', 'string', 'max:50']];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function attributeLanguageRules(string $presence): array
    {
        return ['lang' => [$presence, 'string', Rule::in([config('app.default_language'), ...Language::query()->pluck('code')])]];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function attributeValueRules(): array
    {
        return [
            'attribute_id' => ['required', 'integer', Rule::exists(Attribute::class, 'id')],
            'value' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function colorRules(?Color $color = null): array
    {
        return [
            'name' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:10', Rule::unique(Color::class, 'code')->ignore($color)],
        ];
    }
}
