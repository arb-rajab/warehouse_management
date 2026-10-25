<?php

namespace App\Http\Controllers\Seller;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Auth;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $seller = Auth::user();
        $seller->loadMissing('shop');
        $data['shop'] = $seller->shop;
        $data['products'] = filter_products(Product::where('user_id', $seller->id)->orderBy('num_of_sale', 'desc'))
            ->select(['id', 'name', 'slug', 'thumbnail_img', 'unit_price', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'rating'])
            ->with(['product_translations:id,product_id,lang,name', 'taxes:id,product_id,tax,tax_type'])
            ->limit(12)->get();
        $data['categories'] = Category::query()->select(['id', 'name'])
            ->with('category_translations:id,category_id,lang,name')
            ->whereHas('products', fn ($products) => $products->where('user_id', $seller->id))
            ->withCount(['products as seller_product_count' => fn ($products) => $products->where('user_id', $seller->id)])
            ->get();
        $data['total_sales'] = OrderDetail::where('seller_id', $seller->id)
            ->whereHas('order', fn ($order) => $order->where('payment_status', 'paid'))
            ->sum('price');
        $data['last_7_days_sales'] = Order::where('created_at', '>=', Carbon::now()->subDays(7))
            ->where('seller_id', '=', $seller->id)
            ->where('delivery_status', '=', 'delivered')
            // DATE() is supported by MySQL and SQLite; select and group the same expression.
            ->selectRaw('SUM(grand_total) as total, DATE(created_at) as date')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date) => [Carbon::parse($date)->format('d M') => $total]);

        return view('shop.seller.dashboard', $data);
    }
}
