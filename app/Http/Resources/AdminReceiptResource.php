<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A receipt for the admin web pages: {@see ReceiptResource} with its bill
 * files presented by {@see AdminReceiptFileResource}, so each `url` is the
 * session-authenticated admin download route.
 */
class AdminReceiptResource extends ReceiptResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'files' => $this->whenLoaded('files', fn () => AdminReceiptFileResource::collection($this->files)),
        ];
    }
}
