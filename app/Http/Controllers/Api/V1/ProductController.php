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

    /**
     * A deactivated product (`published = 0`) is returned normally rather than
     * 404ing — a QR label printed while it was active still needs to resolve
     * to "this product isn't sold anymore" (active: false) instead of looking
     * like an unknown/broken label. Only a genuinely nonexistent id 404s.
     */
    public function show(int $product): ProductResource
    {
        $product = Product::query()
            ->select('id', 'name', 'ar_name', 'thumbnail_img', 'published')
            ->with(Product::WITH_DERIVED_ATTRIBUTES)
            ->find($product);

        abort_if($product === null, 404);

        return new ProductResource($product);
    }
}
