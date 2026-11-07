<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\FilterProductAttributeValuesRequest;
use App\Http\Requests\Store\Admin\FilterProductSearchRequest;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Support\SearchOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductSearchController extends Controller implements HasMiddleware
{
    use SearchesOptions;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:order_configuration', only: ['search']),
            new Middleware('permission:add_new_product|product_edit', only: ['attributeValues']),
        ];
    }

    /** The default-product typeahead of the order configuration (StoreSearchSelect): a part of the id. */
    public function search(FilterProductSearchRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $products = Product::query()->select(['id', 'name', 'thumbnail_img'])
            ->with(['product_translations:id,product_id,lang,name', 'thumbnailUpload:id,file_name,external_link']);
        $this->whereWordsMatch($products, ['id'], $term);
        $this->orderByRelevance($products, 'id', $term);

        return $this->optionsPage($products, fn (Product $product): array => SearchOption::configurationProduct($product));
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
