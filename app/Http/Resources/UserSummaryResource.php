<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The id/name pair every payload carries for the user who performed an action —
 * deliberately narrower than UserResource, which also exposes the email and the
 * admin flag and is only used where the user is the subject of the response.
 *
 * @property-read int $id
 * @property-read string $name
 */
class UserSummaryResource extends JsonResource
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
            'name' => $this->name,
        ];
    }
}
