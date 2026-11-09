<?php

namespace App\Http\Resources;

use App\Models\ProductBarcode;
use App\Services\ProductBarcodeLabels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read string|null $image_verification
 * @property-read int $id
 * @property-read string $name
 * @property-read string $ar_name
 * @property-read string|null $image_url
 * @property-read bool $published
 * @property-read int $boxes_count
 * @property-read int|null $minimum_pallets
 * @property-read int $pallets_count
 * @property-read int $full_cells_count
 * @property-read int $opened_cells_count
 * @property-read int $expired_cells_count
 * @property-read int $expiring_soon_count
 * @property-read Collection<int, ProductBarcode> $barcodes
 */
class ProductSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'image_verification' => $this->image_verification,
            'id' => $this->id,
            'name' => $this->name,
            'ar_name' => $this->ar_name,
            'image_url' => $this->image_url,
            'active' => $this->published,
            'boxes_count' => $this->boxes_count,
            'minimum_pallets' => $this->minimum_pallets,
            'pallets_count' => (int) $this->pallets_count,
            'full_cells_count' => (int) $this->full_cells_count,
            'opened_cells_count' => (int) $this->opened_cells_count,
            'expired_cells_count' => (int) $this->expired_cells_count,
            'expiring_soon_count' => (int) $this->expiring_soon_count,
            // The shared table's rows bar the own-id row (the id is already
            // the default barcode) and blanks; `printable` says whether the
            // value, Arabic digits converted, can be drawn as a label.
            'barcodes' => ProductBarcode::listedInWms($this->barcodes)->map(fn (ProductBarcode $barcode): array => self::barcodeShape($barcode))->all(),
        ];
    }

    /**
     * One listed barcode row. Also the shape the mobile add-barcode endpoint
     * answers with, so both lists read the same.
     *
     * @return array{id: int, barcode: string, printable: bool}
     */
    public static function barcodeShape(ProductBarcode $barcode): array
    {
        $labels = app(ProductBarcodeLabels::class);

        return [
            'id' => $barcode->id,
            'barcode' => $barcode->barcode,
            'printable' => $labels->isPrintable($labels->normalize($barcode->barcode)),
        ];
    }
}
