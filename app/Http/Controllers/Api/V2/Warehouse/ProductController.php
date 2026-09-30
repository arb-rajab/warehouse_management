<?php

namespace App\Http\Controllers\Api\V2\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductController extends Controller
{
    public function getProducts()
    {
        $products = Product::select('id', 'serial', 'name', 'ar_name', 'unit_price', 'api_unitId', 'api_unit_name', 'tax', 'tax_type', 'category_id', 'description', 'published', 'variations', 'from_api', 'unit_equal', 'mat_id', 'photos', 'old_photo')
            ->without(['product_translations', 'taxes']) // Exclude taxes
            ->orderBy('published', 'desc')
            ->get();

            foreach($products as $product){
                $product->photos = uploaded_asset($product->photos);
                $product->otajer_photo = uploaded_asset($product->old_photo);
                unset($product->old_photo); // Remove old_photo from the response
            }
        return response()->json(['data' => $products]);
    }
}
