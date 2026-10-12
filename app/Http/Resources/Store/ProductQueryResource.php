<?php

namespace App\Http\Resources\Store;

use App\Models\ProductQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read ProductQuery $resource */
class ProductQueryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $query = $this->resource;

        return [
            'id' => $query->id,
            'user' => $query->user === null ? null : ['id' => $query->user->id, 'name' => $query->user->name],
            'product' => $query->product === null ? null : ['id' => $query->product->id, 'name' => $query->product->getTranslation('name')],
            'question' => $this->plainText($query->question),
            'reply' => $query->reply === null ? null : $this->plainText($query->reply),
            'created_at' => $query->created_at?->toIso8601String(),
        ];
    }

    private function plainText(string $value): string
    {
        return html_entity_decode(strip_tags(clean_html($value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
