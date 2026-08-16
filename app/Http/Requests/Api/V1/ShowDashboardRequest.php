<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersDashboard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowDashboardRequest extends FormRequest
{
    use FiltersDashboard;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->dashboardFilterRules();
    }
}
