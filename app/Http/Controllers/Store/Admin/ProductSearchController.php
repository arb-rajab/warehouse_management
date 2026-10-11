<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterProductAttributeValuesRequest;
use App\Http\Requests\Store\Admin\FilterProductSearchRequest;
use App\Http\Resources\Store\ProductSearchResource;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductSearchController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:order_configuration', only: ['search']),
            new Middleware('permission:add_new_product|product_edit', only: ['attributeValues']),
        ];
    }

    public function search(FilterProductSearchRequest $request): JsonResponse
    {
        $term = $request->validated('query');
        $products = Product::query()->select(['id', 'name', 'mat_id'])
            ->with('product_translations:id,product_id,lang,name')
            ->where('mat_id', 'like', '%'.$term.'%')->orderBy('id')->limit(20)->get();

        return response()->json(ProductSearchResource::collection($products)->resolve($request));
    }

    public function attributeValues(FilterProductAttributeValuesRequest $request): JsonResponse
    {
        $values = AttributeValue::query()->select(['id', 'value'])
            ->where('attribute_id', $request->integer('attribute_id'))->orderBy('id')->get();

        return response()->json($values->map(fn (AttributeValue $value): array => [
            'id' => $value->id,
            'value' => $value->value,
        ]));
    }
}
