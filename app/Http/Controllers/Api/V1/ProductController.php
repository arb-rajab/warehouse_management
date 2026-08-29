<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(FilterProductsRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()
                ->select('id', 'name', 'image_url', 'boxes_count')
                ->searchByName($request->string('q')->value())
                ->orderBy('name')
                ->paginate(20)
        );
    }
}
