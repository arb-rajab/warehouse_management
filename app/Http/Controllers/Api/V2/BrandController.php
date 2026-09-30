<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\BrandCollection;
use App\Models\Brand;
use App\Utility\SearchUtility;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brand_query = Brand::query();
        if($request->name != "" || $request->name != null){
            $brand_query->where('name', 'like', '%'.$request->name.'%');
            SearchUtility::store($request->name);
        }
        return new BrandCollection($brand_query->paginate(10));
    }

    public function top()
    {
        return new BrandCollection(remember_models(Brand::class, 'app.top_brands', 86400, function () {
            return Brand::where('top', 1)->get();
        }));
    }
}
