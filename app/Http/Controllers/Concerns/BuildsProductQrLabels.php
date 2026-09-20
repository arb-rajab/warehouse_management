<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;

/**
 * Shared QR-label building for the product QR-export PDF (Admin\ProductController).
 *
 * One QR encodes one product id, not one physical pallet/box — every unit of
 * that product carries an identical printed label. Only the id is encoded
 * (`warehouseapp://product?id={id}`, the same custom-scheme shape
 * BuildsCellQrLabels uses for cell labels): name/ar_name/image_url can change
 * after a label is printed, so the mobile app resolves current data live via
 * GET /api/v1/products/{id} instead of the label carrying them directly.
 */
trait BuildsProductQrLabels
{
    use BuildsQrLabels;

    /**
     * @return array{label: string, description: string, qrImage: string}
     */
    private function productQrLabel(Product $product): array
    {
        return [
            'label' => $product->name,
            'description' => $this->shapeArabicForPdf(__('messages.product_qr_label_description', [
                'id' => $product->id,
            ])),
            'qrImage' => $this->qrImageDataUri("warehouseapp://product?id={$product->id}"),
        ];
    }
}
