<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteOrdersRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['id' => ['required', 'array', 'min:1', 'max:100'], 'id.*' => ['required', 'integer', 'distinct', 'exists:orders,id']];
    }
}
