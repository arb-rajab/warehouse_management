<?php

namespace App\Http\Controllers\Api\V2\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function getCategories()
    {
        $categories = Category::orderBy('order_level', 'desc')->get();

        return response()->json(['data' => $categories]);
    }
}
