<?php

namespace App\Http\Resources;

use App\Enums\ReceiptStatus;
use App\Models\ReceiptFile;
use App\Models\ReceiptLine;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A truck receipt against a bill. The list carries the header and counts;
 * the detail adds `files` and `lines` (Receipt::withDetails()).
 *
 * @property-read int $id
 * @property-read string $bill_name
 * @property-read string|null $bill_info
 * @property-read ReceiptStatus $status
 * @property-read CarbonImmutable|null $finalized_at
 * @property-read CarbonImmutable|null $created_at
 * @property-read Warehouse $warehouse
 * @property-read User $createdBy
 * @property-read User|null $finalizedBy
 * @property-read Collection<int, ReceiptFile> $files
 * @property-read Collection<int, ReceiptLine> $lines
 */
class ReceiptResource extends JsonResource
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
            'warehouse' => $this->whenLoaded('warehouse', fn () => new WarehouseSummaryResource($this->warehouse)),
            'bill_name' => $this->bill_name,
            'bill_info' => $this->bill_info,
            'status' => $this->status->value,
            'lines_count' => $this->whenCounted('lines'),
            'files_count' => $this->whenCounted('files'),
            'created_by' => $this->whenLoaded('createdBy', fn () => new UserSummaryResource($this->createdBy)),
            'finalized_by' => $this->whenLoaded('finalizedBy', fn () => $this->finalizedBy !== null ? new UserSummaryResource($this->finalizedBy) : null),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'files' => $this->whenLoaded('files', fn () => ReceiptFileResource::collection($this->files)),
            'lines' => $this->whenLoaded('lines', fn () => ReceiptLineResource::collection($this->lines)),
        ];
    }
}
