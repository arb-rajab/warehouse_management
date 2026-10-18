<?php

namespace App\Http\Requests\Concerns;

use App\Models\StoreUser;
use Illuminate\Validation\Rule;

trait ValidatesStoreSeller
{
    /** @return array<string, mixed> */
    protected function sellerRules(?int $userId, bool $passwordRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(StoreUser::class, 'email')->ignore($userId)],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }
}
