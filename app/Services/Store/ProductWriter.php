<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductTax;
use App\Models\ProductTranslation;
use App\Services\ProductTaxService;

/** Related rows shared by physical and digital catalogue transactions. */
abstract class ProductWriter
{
    public function __construct(protected ProductTaxService $taxes) {}

    /**
     * Keeps the stored stock row of each posted variant, updated in place,
     * adds the new ones and deletes the rest.
     *
     * @param  list<array{variant: string, price: mixed, sku: mixed, qty: mixed, image: mixed}>  $rows
     */
    protected function syncStocks(Product $product, array $rows): void
    {
        $stored = $product->stocks()->orderBy('id')->get();
        $kept = [];
        foreach ($rows as $row) {
            $stock = $stored->first(fn (ProductStock $stock): bool => (string) $stock->getAttribute('variant') === $row['variant'] && ! in_array($stock->getKey(), $kept, true))
                ?? new ProductStock;
            $stock->forceFill(['product_id' => $product->id, ...$row])->save();
            $kept[] = $stock->getKey();
        }
        $product->stocks()->whereNotIn('id', $kept)->delete();
    }

    /**
     * Posted taxes are stored through ProductTaxService; on update they first
     * replace every tax row of the product. With no taxes posted (no active
     * tax, or the tax section off), the product's rows stay, as before.
     *
     * @param  array<string, mixed>  $data
     */
    protected function saveTaxes(Product $product, array $data, bool $replace): void
    {
        if (empty($data['tax_id'])) {
            return;
        }
        if ($replace) {
            ProductTax::query()->where('product_id', $product->id)->delete();
        }
        $this->taxes->store(['tax_id' => $data['tax_id'], 'tax' => $data['tax'], 'tax_type' => $data['tax_type'], 'product_id' => $product->id]);
    }

    /** @param array<string, mixed> $data */
    protected function saveTranslation(Product $product, string $lang, array $data): void
    {
        $translation = ProductTranslation::query()->firstOrNew(['product_id' => $product->id, 'lang' => $lang]);
        $translation->forceFill([
            'product_id' => $product->id,
            'lang' => $lang,
            'name' => $data['name'],
            ...(array_key_exists('unit', $data) ? ['unit' => $data['unit']] : []),
            'description' => $data['description'] ?? null,
        ])->save();
    }
}
