<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CellLogAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation for requests that filter by which cell-status-log actions
 * a listing should cover.
 */
trait FiltersByLogAction
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function logActionFilterRules(): array
    {
        return [
            'action' => ['nullable', 'array'],
            'action.*' => [Rule::enum(CellLogAction::class)],
        ];
    }
}
