<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A photo or PDF of a receipt's bill. Never the storage path: `url` is the
 * authenticated download endpoint (admin token required), the only way a
 * bill is read.
 *
 * @property-read int $id
 * @property-read int $receipt_id
 * @property-read string $original_name
 * @property-read string $mime_type
 * @property-read int $size
 * @property-read CarbonImmutable|null $created_at
 */
class ReceiptFileResource extends JsonResource
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
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            /** Bytes stored. */
            'size' => $this->size,
            /** `GET` with the bearer token to read the file. */
            'url' => $this->downloadUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The authenticated download route this payload points at; the admin web
     * resource points at its own session-authenticated one.
     */
    protected function downloadUrl(): string
    {
        return route('api.v1.receipts.files.show', ['receipt' => $this->receipt_id, 'file' => $this->id]);
    }
}
