<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreRole;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    use ValidatesStoreRole;

    /**
     * Get the validation rules that apply to the request.
     *
     * A name in the default language renames the role, so it must stay unique
     * among the store's roles; a name in another language is a translation
     * only, and two roles may translate to the same word.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::when(
                    $this->input('lang') === config('app.default_language'),
                    [Rule::unique(Role::class, 'name')->where('guard_name', 'store')->ignore($role->id)],
                ),
            ],
            ...$this->permissionRules(),
            ...$this->languageRules('required'),
        ];
    }
}
