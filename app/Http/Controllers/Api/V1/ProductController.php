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
        $productPublished = $request->productPublished();

        return ProductResource::collection(
            Product::query()
                ->select('id', 'name', 'ar_name', 'thumbnail_img', 'published')
                ->with(Product::WITH_DERIVED_ATTRIBUTES)
                ->searchByName($request->string('q')->value())
                ->when($productPublished !== null, fn ($query) => $query->where('published', $productPublished))
                ->orderBy('name')
                ->paginate(20)
        );
    }
}
