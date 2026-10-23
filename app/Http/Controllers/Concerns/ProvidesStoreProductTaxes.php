<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\ProductTax;
use App\Models\Tax;
use App\Support\ProductFormNumber;

trait ProvidesStoreProductTaxes
{
    /**
     * The active taxes, by id, with the product's amount and type for each
     * (0 and `amount` when it has none), as the legacy product forms listed
     * them.
     *
     * @return list<array{id: int, name: string, tax: string, tax_type: string}>
     */
    private function taxOptions(?Product $product): array
    {
        $rows = $product ? ProductTax::query()->where('product_id', $product->id)->orderBy('id')->get(['tax_id', 'tax', 'tax_type'])->keyBy('tax_id') : collect();

        return array_values(Tax::query()->where('tax_status', 1)->orderBy('id')->get(['id', 'name'])
            ->map(function (Tax $tax) use ($rows): array {
                $row = $rows->get($tax->getKey());

                return [
                    'id' => (int) $tax->getKey(),
                    'name' => (string) $tax->getAttribute('name'),
                    'tax' => $row ? ProductFormNumber::format($row->getAttribute('tax')) : '0',
                    'tax_type' => $row?->getAttribute('tax_type') === 'percent' ? 'percent' : 'amount',
                ];
            })->all());
    }
}
