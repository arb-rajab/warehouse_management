<?php

namespace App\Http\Controllers\Api\V2\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\WarehouseListRequest;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * One page of categories; `data` is unchanged, the paginator's own keys
     * (`current_page`, `last_page`, …) ride alongside it.
     */
    public function getCategories(WarehouseListRequest $request)
    {
        $categories = Category::orderBy('order_level', 'desc')
            ->orderBy('id')
            ->paginate($request->perPage());

        return response()->json($categories->toArray());
    }
}
