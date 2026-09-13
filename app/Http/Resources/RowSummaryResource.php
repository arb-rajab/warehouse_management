<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The id/letter pair every payload carries for a row that's identified but
 * not itself the subject of the response — deliberately narrower than
 * RowResource, which also requires the `has_pallets` exists-subquery from
 * every caller (see .ai/rules/resources.md) and says nothing about, e.g., a
 * round's coverage or which rows are frozen.
 *
 * @property-read int $id
 * @property-read string $letter
 */
class RowSummaryResource extends JsonResource
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
            'letter' => $this->letter,
        ];
    }
}
