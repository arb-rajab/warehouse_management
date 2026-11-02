<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\WarehouseListRequest;
use App\Models\Product;

class ProductController extends Controller
{
    public function getProducts(WarehouseListRequest $request)
    {
        $products = Product::select('id', 'serial', 'name', 'ar_name', 'unit_price', 'api_unitId', 'api_unit_name', 'tax', 'tax_type', 'category_id', 'description', 'published', 'variations', 'from_api', 'unit_equal', 'photos', 'old_photo')
            ->without(['product_translations', 'taxes']) // Exclude taxes
            ->orderBy('published', 'desc')
            ->orderBy('id')
            ->paginate($request->perPage());

        foreach ($products as $product) {
            $product->mat_id = (string) $product->id;
            $product->photos = uploaded_asset($product->photos);
            $product->otajer_photo = uploaded_asset($product->old_photo);
            unset($product->old_photo); // Remove old_photo from the response
        }

        return response()->json($products->toArray());
    }
}
