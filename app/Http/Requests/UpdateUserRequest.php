<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'max:255', 'confirmed', Password::default()],
            'is_admin' => ['required', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('is_admin')) {
                return;
            }

            /** @var User $user */
            $user = $this->route('user');

            $revokingOwnAdminAccess = $this->user()->is($user) && ! $this->boolean('is_admin');

            if ($revokingOwnAdminAccess) {
                $validator->errors()->add('is_admin', __('messages.admin_cannot_remove_own_access'));
            }
        });
    }
}
