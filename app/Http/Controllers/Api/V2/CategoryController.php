<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\CategoryCollection;
use App\Models\BusinessSetting;
use App\Models\Category;
use Cache;

class CategoryController extends Controller
{

    public function index($parent_id = 0)
    {
        if (request()->has('parent_id') && is_numeric(request()->get('parent_id'))) {
            $parent_id = request()->get('parent_id');
        }

        return Cache::remember("app.categories-$parent_id", 86400, function () use ($parent_id) {
            return new CategoryCollection(Category::orderBy('order_level', 'desc')->where('parent_id', $parent_id)->where('name', "!=", 'Uncategorized')->get());
        });
    }

    public function featured()
    {
        return Cache::remember('app.featured_categories', 86400, function () {
            return new CategoryCollection(Category::orderBy('order_level', 'desc')->where('featured', 1)->where('name', "!=", 'Uncategorized')->get());
        });
    }

    public function home()
    {
        return Cache::remember('app.home_categories', 86400, function () {
            return new CategoryCollection(Category::orderBy('order_level', 'desc')->whereIn('id', json_decode(get_setting('home_categories')))->where('name', "!=", 'Uncategorized')->get());
        });
    }

    public function top()
    {
        return Cache::remember('app.top_categories', 86400, function () {
            return new CategoryCollection(Category::orderBy('order_level', 'desc')->whereIn('id', json_decode(get_setting('home_categories')))->where('name', "!=", 'Uncategorized')->limit(20)->get());
        });
    }
}
