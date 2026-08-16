<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByProductIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowDashboardRequest extends FormRequest
{
    use FiltersByProductIds;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expiring_days' => ['nullable', 'integer', 'min:1'],
            ...$this->productIdsFilterRules(),
        ];
    }
}
