<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marking an order delivered from the trip orders list or, with
 * `return_to=map`, from the retained Blade map picker's order popup.
 */
class MarkTripOrderDeliveredRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['return_to' => ['nullable', 'string', Rule::in(['map'])]];
    }
}
