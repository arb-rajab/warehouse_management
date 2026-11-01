<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\CategoryCollection;
use App\Models\Category;

class SubCategoryController extends Controller
{
    public function index($id)
    {
        return new CategoryCollection(Category::with('category_translations')->where('parent_id', $id)->get());
    }
}
