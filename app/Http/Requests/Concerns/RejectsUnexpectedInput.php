<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** @mixin FormRequest */
trait RejectsUnexpectedInput
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = array_unique(array_map(fn (string $key) => explode('.', $key)[0], array_keys($validator->getRules())));
            foreach (array_diff(array_keys($this->except(['_token', '_method'])), $allowed) as $key) {
                $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
            }
        });
    }
}
