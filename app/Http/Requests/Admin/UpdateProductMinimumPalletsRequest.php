<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductMinimumPalletsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Unlike `UpdateProductBoxCountRequest`'s `boxes_count`, `minimum_pallets`
     * is nullable rather than required: an admin can clear a product's
     * threshold back to "not configured" as well as set one, and the field is
     * `present` so a request that omits it entirely (rather than sending an
     * explicit `null`) is rejected instead of silently no-oping.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'minimum_pallets' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }
}
