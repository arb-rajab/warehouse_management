<?php

namespace App\Http\Requests\Concerns;

use App\Models\Upload;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** @mixin FormRequest */
trait ValidatesSellerProductFields
{
    private function tagsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tags = is_string($value) ? json_decode($value, true) : null;
            if (! is_array($tags) || ! array_is_list($tags) || count($tags) > 100
                || collect($tags)->contains(fn ($tag) => ! is_array($tag) || array_keys($tag) !== ['value'] || ! is_string($tag['value']) || trim($tag['value']) === '' || str_contains($tag['value'], ','))) {
                $fail(__('validation.array', ['attribute' => $attribute]));
            } elseif (mb_strlen(implode(',', array_column($tags, 'value'))) > 500) {
                $fail(__('validation.max.string', ['attribute' => $attribute, 'max' => 500]));
            }
        };
    }

    private function photosRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ids = is_string($value) && preg_match('/^\d+(?:,\d+)*$/D', $value) ? explode(',', $value) : [];
            if ($ids === [] || count($ids) > 50 || count(array_unique($ids)) !== count($ids)
                || Upload::query()->where('user_id', $this->user('store')?->id)->where('type', 'image')->whereIn('id', $ids)->count() !== count($ids)) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }
}
