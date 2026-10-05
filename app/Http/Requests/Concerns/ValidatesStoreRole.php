<?php

namespace App\Http\Requests\Concerns;

use App\Models\Language;
use App\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The store role form's shared fields: the checked store permissions, and the
 * language a role name is written in (the store's default language names the
 * role itself, any other only translates it).
 */
trait ValidatesStoreRole
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function permissionRules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists(Permission::class, 'id')->where('guard_name', 'store')],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function languageRules(string $presence): array
    {
        return [
            'lang' => [$presence, 'string', Rule::in([config('app.default_language'), ...Language::query()->pluck('code')])],
        ];
    }
}
