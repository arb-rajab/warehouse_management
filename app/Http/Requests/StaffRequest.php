<?php

namespace App\Http\Requests;

use App\Models\Staff;
use App\Models\StoreUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The staff screens' create and edit forms. An email must be free of every
 * other account (the edited member's own excepted), and a password is
 * required on create and optional, but just as strong, on edit.
 */
class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isCreating = $this->isMethod('POST');
        $editedUserId = $isCreating ? null : Staff::whereKey($this->route('staff'))->value('user_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(StoreUser::class, 'email')->ignore($editedUserId)],
            'mobile' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'integer'],
            'password' => [$isCreating ? 'required' : 'nullable', 'string', 'max:255', Password::default()],
        ];
    }
}
