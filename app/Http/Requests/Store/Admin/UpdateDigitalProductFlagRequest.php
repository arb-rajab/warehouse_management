<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The published, featured and today's-deal switches of the digital products
 * list: each posts the switch's new state as `status`.
 */
class UpdateDigitalProductFlagRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['status' => ['required', 'boolean']];
    }
}
