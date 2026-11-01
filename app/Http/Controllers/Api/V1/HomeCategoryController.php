<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\HomeCategoryCollection;
use App\Models\HomeCategory;

class HomeCategoryController extends Controller
{
    public function index()
    {
        return new HomeCategoryCollection(HomeCategory::with('category')->get());
    }
}
