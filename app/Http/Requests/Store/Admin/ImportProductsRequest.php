<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesProductSpreadsheet;
use Illuminate\Foundation\Http\FormRequest;

class ImportProductsRequest extends FormRequest
{
    use ValidatesProductSpreadsheet;

    /** @return array<string, mixed> Get the validation rules that apply to the request. */
    public function rules(): array
    {
        return $this->productSpreadsheetRules();
    }
}
