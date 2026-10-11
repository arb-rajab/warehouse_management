<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read mixed $report_value
 * @property-read Product $resource
 */
class ProductReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->resource->getTranslation('name'),
            'value' => (int) $this->report_value,
        ];
    }
}
