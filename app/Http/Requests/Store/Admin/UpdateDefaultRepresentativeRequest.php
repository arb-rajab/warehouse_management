<?php

namespace App\Http\Requests\Store\Admin;

use App\Models\StoreUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDefaultRepresentativeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['default_representative_check' => ['required', 'boolean'], 'default_representative_id' => ['required', 'integer', Rule::exists(StoreUser::class, 'id')->where('is_rep', 1)]];
    }
}
