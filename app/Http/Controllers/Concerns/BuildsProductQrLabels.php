<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;

/**
 * Shared QR-label building for the single-product QR-export image
 * (Admin\ProductController) — see BuildsQrLabels::qrLabelImage().
 *
 * One QR encodes one product id, not one physical pallet/box — every unit
 * of that product carries an identical printed label. Only the id is
 * encoded (`warehouseapp://product?id={id}`, the same custom-scheme shape
 * BuildsCellQrLabels uses for cell labels): name/ar_name/image_url can
 * change after a label is printed, so the mobile app resolves current data
 * live via GET /api/v1/products/{id} instead of the label carrying them
 * directly. The printed name/ar_name text is purely for a human to verify
 * the sticker against the box before scanning — it isn't what the scan
 * itself resolves.
 */
trait BuildsProductQrLabels
{
    use BuildsQrLabels;

    private function productQrLabelImage(Product $product, int $qrWidth, int $qrHeight): string
    {
        return $this->qrLabelImage(
            "warehouseapp://product?id={$product->id}",
            $product->name,
            $product->ar_name !== '' ? $product->ar_name : null,
            'rtl',
            $qrWidth,
            $qrHeight,
        );
    }
}
