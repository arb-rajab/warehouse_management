<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\CategoryCollection;
use App\Models\BusinessSetting;
use App\Models\Category;

class CategoryController extends Controller
{

    public function index($parent_id = 0)
    {
        if (request()->has('parent_id') && is_numeric(request()->get('parent_id'))) {
            $parent_id = request()->get('parent_id');
        }

        return new CategoryCollection(remember_models(Category::class, "app.categories-$parent_id", 86400, function () use ($parent_id) {
            return Category::orderBy('order_level', 'desc')->where('parent_id', $parent_id)->where('name', "!=", 'Uncategorized')->get();
        }, ['category_translations']));
    }

    public function featured()
    {
        return new CategoryCollection(remember_models(Category::class, 'app.featured_categories', 86400, function () {
            return Category::orderBy('order_level', 'desc')->where('featured', 1)->where('name', "!=", 'Uncategorized')->get();
        }, ['category_translations']));
    }

    public function home()
    {
        return new CategoryCollection(remember_models(Category::class, 'app.home_categories', 86400, function () {
            return Category::orderBy('order_level', 'desc')->whereIn('id', $this->homeCategoryIds())->where('name', "!=", 'Uncategorized')->get();
        }, ['category_translations']));
    }

    public function top()
    {
        return new CategoryCollection(remember_models(Category::class, 'app.top_categories', 86400, function () {
            return Category::orderBy('order_level', 'desc')->whereIn('id', $this->homeCategoryIds())->where('name', "!=", 'Uncategorized')->limit(20)->get();
        }, ['category_translations']));
    }

    /**
     * The ids chosen in the website home settings. The setting is absent until
     * an admin saves it, which must give an empty list, not a null `whereIn`.
     *
     * @return list<int|string>
     */
    private function homeCategoryIds(): array
    {
        $ids = json_decode((string) get_setting('home_categories'), true);

        return is_array($ids) ? array_values($ids) : [];
    }
}
