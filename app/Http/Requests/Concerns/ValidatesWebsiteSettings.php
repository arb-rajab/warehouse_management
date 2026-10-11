<?php

namespace App\Http\Requests\Concerns;

use App\Models\Upload;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** @mixin FormRequest */
trait ValidatesWebsiteSettings
{
    /** @return array<int, mixed> */
    protected function imageSettingRules(): array
    {
        $user = $this->user('store');

        return ['nullable', 'integer', Rule::exists('uploads', 'id')->where('type', 'image')->where(fn (Builder $query) => $query->when((! $user->can('view_uploads') || ! $user->can('viewAny', Upload::class)), fn (Builder $query) => $query->where('user_id', $user->id)))];
    }

    /** @return array<int, string> */
    protected function linkSettingRules(): array
    {
        return ['nullable', 'string', 'max:2048', 'regex:/^(?:https?:\/\/[^\s]+|\/(?!\/)[^\s]*|#[^\s]*)$/i'];
    }

    /** @return array<int, mixed> */
    protected function uploadIdsRules(?string $type = null): array
    {
        return ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail) use ($type): void {
            if (! is_string($value) || preg_match('/^\d+(?:,\d+)*$/', $value) !== 1) {
                $fail(__('validation.regex', ['attribute' => $attribute]));

                return;
            }
            $ids = array_map(intval(...), explode(',', $value));
            $user = $this->user('store');
            $found = Upload::query()->whereIn('id', $ids)->when($type !== null, fn ($query) => $query->where('type', $type))->when((! $user->can('view_uploads') || ! $user->can('viewAny', Upload::class)), fn ($query) => $query->where('user_id', $user->id))->count();
            if (count($ids) > 20 || count(array_unique($ids)) !== count($ids) || $found !== count($ids)) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        }];
    }

    /** @param list<string> $allowed */
    protected function refuseUnexpectedSettingInput(Validator $validator, array $allowed = ['settings']): void
    {
        foreach (array_diff(array_keys($this->except(['_token', '_method'])), $allowed) as $key) {
            $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
        }
    }

    protected function validatePairedSettings(Validator $validator, string $first, string $second): void
    {
        $left = $this->input('settings.'.$first);
        $right = $this->input('settings.'.$second);
        if (is_array($left) && is_array($right) && count($left) !== count($right)) {
            $validator->errors()->add('settings.'.$second, __('validation.size.array', ['attribute' => $second, 'size' => count($left)]));
        }
    }
}
