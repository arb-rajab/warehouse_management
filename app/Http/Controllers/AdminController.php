<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Artisan;
use Cache;
//use CoreComponentRepository;

class AdminController extends Controller
{
    /**
     * Show the admin dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function admin_dashboard(Request $request)
    {
     //   CoreComponentRepository::initializeCache();
        $root_categories = Category::where('level', 0)->get();
        $total_visits = json_decode(get_setting('total_visits'), true);

        $cached_graph_data = Cache::remember('cached_graph_data', 86400, function() use ($root_categories, $total_visits){
            $num_of_sale_data = null;
            $qty_data = null;
            $num_of_visits_data = null;

            foreach ($root_categories as $key => $category){
                $category_ids = \App\Utility\CategoryUtility::children_ids($category->id);
                $category_ids[] = $category->id;

                $products = Product::with('stocks')->whereIn('category_id', $category_ids)->get();
                $qty = 0;
                $sale = 0;
                foreach ($products as $key => $product) {
                    $sale += $product->num_of_sale;
                    foreach ($product->stocks as $key => $stock) {
                        $qty += $stock->qty;
                    }
                }
                $qty_data .= $qty.',';
                $num_of_sale_data .= $sale.',';
            }

            foreach ($total_visits as $country => $total_visit){
                $num_of_visits_data .= $total_visit.',';
            }

            $item['num_of_sale_data'] = $num_of_sale_data;
            $item['qty_data'] = $qty_data;
            $item['num_of_visits_data'] = $num_of_visits_data;

            return $item;
        });

        return view('backend.dashboard', compact('root_categories', 'cached_graph_data', 'total_visits'));
    }

    function clearCache(Request $request)
    {
        Artisan::call('optimize:clear');
        flash(translate('Cache cleared successfully'))->success();
        return back();
    }
}
