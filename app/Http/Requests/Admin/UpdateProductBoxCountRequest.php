<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductBoxCountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Deliberately not reusing `ValidatesBoxesCount`: that trait's
     * `boxes_count` is how many boxes a worker is removing from a pallet,
     * which happens to share these rules today but is a different quantity
     * from a product's pallet capacity and free to diverge.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'boxes_count' => ['required', 'integer', 'min:1'],
        ];
    }
}
