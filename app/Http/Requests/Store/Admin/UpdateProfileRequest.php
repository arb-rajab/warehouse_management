<?php

namespace App\Http\Requests\Store\Admin;

use App\Models\StoreUser;
use App\Models\Upload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The store back office's own-profile form: a signed-in staff member edits
 * their own account only, so another account's id in the URL is a 403 before
 * any validation runs.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof StoreUser && $profile->id === $this->user('store')?->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(StoreUser::class, 'email')->ignore($this->user('store')->id)],
            'new_password' => ['nullable', 'string', 'max:255', 'same:confirm_password', Password::default()],
            'avatar' => ['nullable', 'integer', Rule::exists(Upload::class, 'id')->where('type', 'image')],
        ];
    }
}
