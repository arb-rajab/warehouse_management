<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use App\Models\UserPageView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read UserPageView $resource */
class CustomerPageViewResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return ['id' => $view->viewable_id, 'kind' => $view->viewable_type === Product::class ? 'products' : 'categories', 'name' => $view->getAttribute('view_name'), 'slug' => $view->getAttribute('product_slug'), 'view_count' => $view->view_count, 'last_viewed_at' => $view->last_viewed_at];
    }
}
