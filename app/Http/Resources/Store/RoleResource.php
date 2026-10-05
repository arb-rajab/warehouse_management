<?php

namespace App\Http\Resources\Store;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A store role as the back office lists it: its name in the current
 * language (role_translations, falling back to the role's own name).
 *
 * @property-read int $id
 * @property-read Role $resource
 */
class RoleResource extends JsonResource
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
        ];
    }
}
