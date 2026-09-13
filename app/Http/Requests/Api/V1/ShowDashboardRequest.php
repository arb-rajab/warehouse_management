<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByProductStatus;
use App\Http\Requests\Concerns\FiltersDashboard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowDashboardRequest extends FormRequest
{
    use FiltersByProductStatus;
    use FiltersDashboard;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->dashboardFilterRules(),
            ...$this->productStatusFilterRules(),
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveProductStatusFilter();
    }
}
