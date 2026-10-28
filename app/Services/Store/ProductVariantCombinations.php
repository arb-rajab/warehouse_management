<?php

namespace App\Services\Store;

use App\Models\Color;
use App\Models\Product;
use App\Utility\ProductUtility;
use Illuminate\Validation\ValidationException;

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
    /** A safe legacy wire key; the stored variant name remains unchanged. */
    public static function inputKey(string $variant): string
    {
        if (preg_match('/[\[\]*]/', $variant) || str_contains($variant, '\\')) {
            return 'variant_'.hash('sha256', $variant);
        }

        return str_replace(['.', ' '], '_', $variant);
    }

    /** @return list<string> Existing stock names the stored choices cannot generate. */
    public function unmatchedStocks(Product $product): array
    {
        $colors = json_decode((string) $product->getAttribute('colors'), true);
        $choices = json_decode((string) $product->getAttribute('choice_options'), true);
        $colors = is_array($colors) ? array_values(array_filter($colors, 'is_string')) : [];
        $values = array_map(fn ($choice) => is_array($choice) && is_array($choice['values'] ?? null) ? array_values(array_filter($choice['values'], 'is_string')) : [], is_array($choices) ? $choices : []);
        // Malformed imported data must not generate an unbounded product.
        $names = array_product(array_map('count', [$colors ?: [null], ...$values])) <= 500 ? $this->names($colors, array_values($values)) : [];
        $names = $names === [] ? [''] : $names;

        return array_values($product->stocks()->whereNotIn('variant', $names)->pluck('variant')->map(fn ($name) => (string) $name)->all());
    }

    /**
     * Refuse implicit loss of already inconsistent legacy stock; reconciliation is explicit.
     *
     * @param  list<string>  $postedNames
     */
    public function assertLegacyStocksRetained(Product $product, array $postedNames): void
    {
        if (array_diff($this->unmatchedStocks($product), $postedNames ?: ['']) !== []) {
            throw ValidationException::withMessages(['choice_options' => __('seller.products.stock_choices_mismatch')]);
        }
    }

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
