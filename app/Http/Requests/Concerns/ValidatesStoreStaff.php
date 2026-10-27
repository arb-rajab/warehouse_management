<?php

namespace App\Http\Requests\Concerns;

use App\Models\Role;
use App\Models\StoreUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The store staff form's fields, shared by Store\Admin\StoreStaffRequest and
 * UpdateStaffRequest. A staff member's email must be free of every other store
 * account, their role must be a store role other than Super Admin, and a
 * password is required on create and optional, but just as strong, on edit.
 * The role must also be one the signed-in staff member may give: nothing
 * beyond their own permissions (App\Support\StaffPrivileges).
 */
trait ValidatesStoreStaff
{
    /**
     * @param  array<int, int|string>  $allowedRoleIds  the roles the signed-in staff member may give (App\Support\StaffPrivileges)
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function staffRules(?int $editedUserId, bool $passwordRequired, array $allowedRoleIds): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(StoreUser::class, 'email')->ignore($editedUserId)],
            'mobile' => ['required', 'string', 'max:255'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists(Role::class, 'id')->where('guard_name', 'store')->whereNot('name', Role::SUPER_ADMIN),
                Rule::in($allowedRoleIds),
            ],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'max:255', 'confirmed', Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function staffMessages(): array
    {
        return ['role_id.in' => __('messages.staff_role_not_assignable')];
    }
}
