<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCustomersRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ['id' => ['required', 'array', 'min:1', 'max:100'], 'id.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('user_type', 'customer')]];
    }
}
