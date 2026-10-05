<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreRole;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    use ValidatesStoreRole;

    /**
     * Get the validation rules that apply to the request.
     *
     * The 50-character limit is role_translations.name's column width: the
     * name is written there too, in the default language.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(Role::class, 'name')->where('guard_name', 'store')],
            ...$this->permissionRules(),
        ];
    }
}
