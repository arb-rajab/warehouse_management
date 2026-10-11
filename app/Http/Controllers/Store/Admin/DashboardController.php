<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreUser;
use App\Utility\CategoryUtility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller implements HasMiddleware
{
    public const string GRAPH_CACHE_KEY = 'store.admin.dashboard.graphs.v1';

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:admin_dashboard', only: ['index'])];
    }

    public function index(): Response
    {
        $categories = Category::query()->select(['id', 'name'])->where('level', 0)
            ->with('category_translations:id,category_id,lang,name')->orderBy('id')->get();
        $graphs = Cache::remember(self::GRAPH_CACHE_KEY, 86400, function () use ($categories): array {
            return $categories->map(function (Category $category): array {
                $ids = [...CategoryUtility::children_ids($category->id), $category->id];

                return ['id' => $category->id,
                    'sales' => (int) Product::query()->whereIn('category_id', $ids)->sum('num_of_sale'),
                    'stock' => (int) DB::table('product_stocks')->join('products', 'products.id', '=', 'product_stocks.product_id')->whereIn('products.category_id', $ids)->sum('product_stocks.qty')];
            })->all();
        });
        $graphsById = collect($graphs)->keyBy('id');

        return Inertia::render('Store/Admin/Dashboard/Index', [
            'stats' => ['customers' => StoreUser::query()->where('user_type', 'customer')->whereNotNull('email_verified_at')->count(),
                'orders' => Order::query()->count(), 'categories' => Category::query()->count(), 'brands' => Brand::query()->count(),
                'products' => Product::query()->count(), 'active_products' => Product::query()->where('published', 1)->count(),
                'api_products' => Product::query()->where('from_api', 1)->count(), 'local_products' => Product::query()->where('from_api', 0)->count()],
            'charts' => ['labels' => $categories->map(fn (Category $category) => $category->getTranslation('name'))->all(),
                'sales' => $categories->map(fn (Category $category) => (int) ($graphsById->get($category->id)['sales'] ?? 0))->all(),
                'stock' => $categories->map(fn (Category $category) => (int) ($graphsById->get($category->id)['stock'] ?? 0))->all()],
            'topProducts' => filter_products(Product::query()->select(['id', 'name', 'ar_name', 'slug', 'thumbnail_img', 'unit_price', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'rating'])->where('published', 1)->whereNotNull('slug')->where('slug', '!=', '')->orderByDesc('num_of_sale')->orderBy('id'))->with('thumbnailUpload:id,external_link,file_name', 'taxes')->limit(12)->get()->map(fn (Product $product): array => [
                'id' => $product->id, 'name' => app()->getLocale() === 'ar' ? ($product->ar_name ?: $product->name) : $product->name,
                'url' => route('product', $product->getAttribute('slug')), 'image' => $product->image_url,
                'base_price' => home_base_price($product), 'price' => home_discounted_base_price($product), 'rating' => (float) $product->getAttribute('rating'),
            ])->all(),
            'smtp_missing' => (bool) auth('store')->user()?->can('smtp_settings') && ! config('mail.mailers.smtp.username') && ! config('mail.mailers.smtp.password'),
        ]);
    }

    public function clearCache(): RedirectResponse
    {
        Cache::forget(self::GRAPH_CACHE_KEY);
        Cache::forget('cached_graph_data');

        return redirect()->route('shop.admin.dashboard');
    }
}
