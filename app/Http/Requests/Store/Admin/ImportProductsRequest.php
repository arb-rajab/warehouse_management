<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportProductsRequest extends FormRequest
{
    /** @return array<string, mixed> Get the validation rules that apply to the request. */
    public function rules(): array
    {
        return ['bulk_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']];
    }
}
