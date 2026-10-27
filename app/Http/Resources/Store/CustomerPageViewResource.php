<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use App\Models\UserPageView;
use App\Support\StorefrontUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read UserPageView $resource */
class CustomerPageViewResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $product = $view->getRelation('viewedProduct');

        return ['id' => $view->viewable_id, 'kind' => $view->viewable_type === Product::class ? 'products' : 'categories', 'name' => $view->getAttribute('view_name'), 'slug' => $view->getAttribute('product_slug'), 'public_url' => $product instanceof Product ? StorefrontUrl::to($product, false) : null, 'view_count' => $view->view_count, 'last_viewed_at' => $view->last_viewed_at];
    }
}
