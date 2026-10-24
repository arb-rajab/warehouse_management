<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreStaff;
use App\Support\StaffPrivileges;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    use ValidatesStoreStaff;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowed = (new StaffPrivileges($this->user('store')))->assignableRoles()->modelKeys();

        return $this->staffRules(editedUserId: null, passwordRequired: true, allowedRoleIds: $allowed);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->staffMessages();
    }
}
