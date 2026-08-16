<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared filter validation for requests showing dashboard stats (the admin
 * page and the mobile API endpoint).
 */
trait FiltersDashboard
{
    use FiltersByProductIds;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function dashboardFilterRules(): array
    {
        return [
            'expiring_days' => ['nullable', 'integer', 'min:1'],
            ...$this->productIdsFilterRules(),
        ];
    }
}
