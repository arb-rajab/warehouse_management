<?php

namespace App\Services\Store;

use App\Models\Color;
use App\Utility\ProductUtility;

/**
 * The variant rows a product's colours and attribute values generate, as the
 * Blade SKU table (`shop.backend.product.products.sku_combinations`) and
 * ProductStockService name them.
 *
 * The rows are the cartesian product of the chosen colours (first, when any)
 * and each chosen attribute's values, in the order they were chosen
 * (ProductUtility::makeCombinations()). A variant is named by its parts
 * joined with `-`: a colour by its name as stored, every other part with its
 * spaces removed. A row whose name would be empty is left out, as the table
 * left it out.
 */
class ProductVariantCombinations
{
    /**
     * @param  list<string>  $colorCodes  the chosen colour codes, none when colours are off
     * @param  list<list<string>>  $choiceValues  each chosen attribute's values, in order
     * @return list<string>
     */
    public function names(array $colorCodes, array $choiceValues): array
    {
        $options = $colorCodes === [] ? $choiceValues : [$colorCodes, ...$choiceValues];
        if ($options === []) {
            return [];
        }
        // ProductUtility::get_combination_string() names a colour by the
        // first `colors` row with its code.
        $colorNames = $colorCodes === [] ? [] : Color::query()->whereIn('code', $colorCodes)->orderByDesc('id')
            ->pluck('name', 'code')->all();
        $names = [];
        foreach (ProductUtility::makeCombinations($options) as $combination) {
            $name = '';
            foreach ($combination as $key => $item) {
                if ($key > 0) {
                    $name .= '-'.str_replace(' ', '', $item);
                } else {
                    $name .= $colorCodes === [] ? str_replace(' ', '', $item) : (string) ($colorNames[$item] ?? '');
                }
            }
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
