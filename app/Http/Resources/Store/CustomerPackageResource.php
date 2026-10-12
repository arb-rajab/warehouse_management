<?php

namespace App\Http\Resources\Store;

use App\Models\CustomerPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $name
 * @property-read string|float|null $amount
 * @property-read int|null $product_upload
 * @property-read string|null $logo
 *
 * @mixin CustomerPackage
 */
class CustomerPackageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'localized_name' => $this->getTranslation('name'),
            'amount' => (string) $this->amount,
            'formatted_amount' => single_price($this->amount),
            'product_upload' => (int) $this->product_upload,
            'logo' => $this->logo === null ? null : (string) $this->logo,
            'logo_url' => uploaded_asset($this->logo),
        ];
    }
}
