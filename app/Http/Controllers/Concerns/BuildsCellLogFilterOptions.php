<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\CellLogAction;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;

/**
 * Shared `filterOptions` shape for admin pages that filter cell-status-log
 * activity by row/column, product, user, and action — used by both
 * Admin\ProductController and Admin\CellStatusLogController so the two
 * filter dropdowns stay in lockstep.
 */
trait BuildsCellLogFilterOptions
{
    /**
     * @param  list<int>|null  $productIds
     * @return array<string, mixed>
     */
    private function productRowUserActionFilterOptions(?array $productIds): array
    {
        return [
            ...Row::filterOptions(),
            'products' => Product::selectedOptions($productIds ?? []),
            'users' => User::filterOptions(),
            'actions' => array_column(CellLogAction::cases(), 'value'),
        ];
    }
}
