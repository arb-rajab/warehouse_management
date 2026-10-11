<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $code
 * @property-read string|null $app_lang_code
 * @property-read int $rtl
 * @property-read bool $status
 */
class LanguageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'app_lang_code' => $this->app_lang_code,
            'rtl' => (bool) $this->rtl,
            'status' => (bool) $this->status,
            'is_default' => $this->code === config('app.default_language'),
            'is_english' => $this->code === 'en',
        ];
    }
}
