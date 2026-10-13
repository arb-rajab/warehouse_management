<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOffersCatalogRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['catalog' => ['required', 'file', 'mimetypes:application/pdf', 'max:51200']];
    }
}
