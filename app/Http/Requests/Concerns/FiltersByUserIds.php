<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation for requests that filter a listing by the users who
 * performed the listed activity.
 */
trait FiltersByUserIds
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function userIdsFilterRules(): array
    {
        return [
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer', Rule::exists(User::class, 'id')],
        ];
    }
}
