<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreStaff;
use App\Models\Staff;
use App\Support\StaffPrivileges;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    use ValidatesStoreStaff;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Staff $staff */
        $staff = $this->route('staff');

        // Nobody changes their own role; for anyone else, only a role within the editor's permissions.
        $allowed = $staff->user_id === $this->user('store')?->id
            ? [$staff->role_id]
            : (new StaffPrivileges($this->user('store')))->assignableRoles()->modelKeys();

        return $this->staffRules(editedUserId: $staff->user_id, passwordRequired: false, allowedRoleIds: $allowed);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->staffMessages();
    }
}
