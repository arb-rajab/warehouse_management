<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\DashboardProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller implements HasMiddleware
{
    public const string GRAPH_CACHE_KEY = 'store.admin.dashboard.graphs.v2';

    /** @return list<Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:admin_dashboard', only: ['index'])];
    }

    public function index(Request $request): Response
    {
        $graphs = Cache::remember(self::GRAPH_CACHE_KEY, 86400, fn (): array => $this->graphTotals());
        $categories = Category::query()->select(['id', 'name'])->where('level', 0)
            ->with('category_translations:id,category_id,lang,name')->orderBy('id')->get();
        $labels = $categories->map(fn (Category $category): string => (string) $category->getTranslation('name'))->all();
        $products = filter_products(Product::query()->select([
            'id', 'name', 'slug', 'thumbnail_img', 'unit_price', 'discount', 'discount_type',
            'discount_start_date', 'discount_end_date', 'rating',
        ])->where('published', 1))->with([
            'product_translations:id,product_id,lang,name',
            'taxes:id,product_id,tax,tax_type',
            'thumbnailUpload:id,file_name,external_link',
        ])->orderByDesc('num_of_sale')->orderBy('id')->limit(12)->get();

        return Inertia::render('Store/Admin/Dashboard/Index', [
            'stats' => [
                'customers' => StoreUser::query()->where('user_type', 'customer')->whereNotNull('email_verified_at')->count(),
                'orders' => Order::query()->count(),
                'categories' => Category::query()->count(),
                'brands' => Brand::query()->count(),
            ],
            'series' => [
                'products' => [Product::query()->count(), Product::query()->where('published', 1)->count()],
                'sources' => [Product::query()->where('from_api', 1)->count(), Product::query()->where('from_api', 0)->count()],
                'sales' => ['labels' => $labels, 'values' => $categories->map(fn (Category $category): int => $graphs['categories'][$category->id]['sales'] ?? 0)->all()],
                'stock' => ['labels' => $labels, 'values' => $categories->map(fn (Category $category): int => $graphs['categories'][$category->id]['stock'] ?? 0)->all()],
                'visits' => $graphs['visits'],
            ],
            'topProducts' => DashboardProductResource::collection($products),
            'showSmtpAlert' => $request->user()->can('smtp_settings')
                && config('mail.mailers.smtp.username') == null
                && config('mail.mailers.smtp.password') == null,
        ]);
    }

    /** @return array{categories: array<int, array{sales: int, stock: int}>, visits: array{labels: list<string>, values: list<int>}} */
    private function graphTotals(): array
    {
        $categories = Category::query()->without('category_translations')->select(['id', 'parent_id', 'level'])->orderBy('id')->get();
        $children = $categories->groupBy('parent_id');
        $sales = DB::table('products')->select('category_id')->selectRaw('SUM(num_of_sale) AS total')->groupBy('category_id')->pluck('total', 'category_id');
        // Aggregate stock separately: joining it into the sales query would
        // multiply a product's sales by its number of variants.
        $stock = DB::table('product_stocks')->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->select('products.category_id')->selectRaw('SUM(product_stocks.qty) AS total')->groupBy('products.category_id')->pluck('total', 'category_id');
        $totals = [];
        foreach ($categories->where('level', 0) as $root) {
            $pending = [$root->id];
            $seen = [];
            $totals[$root->id] = ['sales' => 0, 'stock' => 0];
            while ($pending !== []) {
                $id = array_pop($pending);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $totals[$root->id]['sales'] += (int) ($sales[$id] ?? 0);
                $totals[$root->id]['stock'] += (int) ($stock[$id] ?? 0);
                foreach ($children->get($id, collect()) as $child) {
                    $pending[] = $child->id;
                }
            }
        }
        $visits = json_decode(get_setting('total_visits', '[]'), true);

        return [
            'categories' => $totals,
            'visits' => ['labels' => array_map(strval(...), array_keys($visits)), 'values' => array_map(intval(...), array_values($visits))],
        ];
    }

    public function clearCache(): RedirectResponse
    {
        Cache::forget(self::GRAPH_CACHE_KEY);
        Cache::forget('cached_graph_data');

        return redirect()->route('shop.admin.dashboard');
    }
}
