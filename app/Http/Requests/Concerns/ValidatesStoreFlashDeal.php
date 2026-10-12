<?php

namespace App\Http\Requests\Concerns;

use App\Models\Language;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesStoreFlashDeal
{
    use ValidatesStoreCampaign;

    /** @return array<string, mixed> */
    protected function flashDealRules(bool $updating): array
    {
        return $this->campaignRules(50) + [
            'text_color' => ['required', Rule::in(['dark', 'light'])],
            'background_color' => ['required', 'string', 'max:255', 'regex:/^(#[a-fA-F0-9]{3}|#[a-fA-F0-9]{6}|[a-zA-Z]+)$/'],
            'lang' => [$updating ? 'required' : 'nullable', 'string', Rule::in([config('app.default_language'), ...Language::query()->pluck('code')])],
        ];
    }

    /** @return array<Closure(Validator): void> */
    public function after(): array
    {
        return $this->campaignPercentages();
    }
}
