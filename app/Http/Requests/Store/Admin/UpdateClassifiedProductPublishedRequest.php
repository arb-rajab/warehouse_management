<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassifiedProductPublishedRequest extends FormRequest
{
    /** @return array<string, mixed> Get the validation rules that apply to the request. */
    public function rules(): array
    {
        return ['status' => ['required', 'boolean']];
    }
}
