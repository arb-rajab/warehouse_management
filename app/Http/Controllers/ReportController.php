<?php

namespace App\Http\Controllers;

use App\Models\CommissionHistory;
use App\Models\Product;
use App\Models\Search;
use App\Models\Shop;
use App\Models\StoreUser;
use App\Models\Wallet;
use Auth;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReportController extends Controller implements HasMiddleware
{
    /**
     * Rules for the filters the history reports take: the date picker's
     * "YYYY-MM-DD / YYYY-MM-DD" range, start not after end, and the account ids.
     *
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return [
            'date_range' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $bounds = explode(' / ', $value);

                if (count($bounds) !== 2 || ! $this->areOrderedDates(...$bounds)) {
                    $fail(translate('The date range must be two valid dates, the start not after the end.'));
                }
            }],
            'seller_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
        ];
    }

    private function areOrderedDates(string $start, string $end): bool
    {
        $start = $this->parseDay($start);
        $end = $this->parseDay($end);

        return $start !== null && $end !== null && $start <= $end;
    }

    private function parseDay(string $day): ?Carbon
    {
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $day);
        } catch (InvalidFormatException) {
            return null;
        }

        return $parsed instanceof Carbon && $parsed->format('Y-m-d') === $day ? $parsed : null;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:in_house_product_sale_report', only: ['in_house_sale_report']),
            new Middleware('permission:seller_products_sale_report', only: ['seller_sale_report']),
            new Middleware('permission:products_stock_report', only: ['stock_report']),
            new Middleware('permission:product_wishlist_report', only: ['wish_report']),
            new Middleware('permission:user_search_report', only: ['user_search_report']),
            new Middleware('permission:commission_history_report', only: ['commission_history']),
            new Middleware('permission:wallet_transaction_report', only: ['wallet_transaction_history']),
        ];
    }
    public function stock_report(Request $request)
    {
        $sort_by =null;
        $products = Product::orderBy('created_at', 'desc');
        if ($request->has('category_id')){
            $sort_by = $request->category_id;
            $products = $products->where('category_id', $sort_by);
        }
        $products = $products->paginate(15);
        return view('shop.backend.reports.stock_report', compact('products','sort_by'));
    }

    public function in_house_sale_report(Request $request)
    {
        $sort_by =null;
        $products = Product::orderBy('num_of_sale', 'desc')->where('added_by', 'admin');
        if ($request->has('category_id')){
            $sort_by = $request->category_id;
            $products = $products->where('category_id', $sort_by);
        }
        $products = $products->paginate(15);
        return view('shop.backend.reports.in_house_sale_report', compact('products','sort_by'));
    }

    public function seller_sale_report(Request $request)
    {
        $sort_by =null;
        // $sellers = StoreUser::where('user_type', 'seller')->orderBy('created_at', 'desc');
        $sellers = Shop::with('user')->orderBy('created_at', 'desc');
        if ($request->has('verification_status')){
            $sort_by = $request->verification_status;
            $sellers = $sellers->where('verification_status', $sort_by);
        }
        $sellers = $sellers->paginate(10);
        return view('shop.backend.reports.seller_sale_report', compact('sellers','sort_by'));
    }

    public function wish_report(Request $request)
    {
        $sort_by =null;
        $products = Product::orderBy('created_at', 'desc');
        if ($request->has('category_id')){
            $sort_by = $request->category_id;
            $products = $products->where('category_id', $sort_by);
        }
        $products = $products->paginate(10);
        return view('shop.backend.reports.wish_report', compact('products','sort_by'));
    }

    public function user_search_report(Request $request){
        $searches = Search::orderBy('count', 'desc')->paginate(10);
        return view('shop.backend.reports.user_search_report', compact('searches'));
    }

    public function commission_history(Request $request) {
        $seller_id = null;
        $date_range = null;

        $request->validate($this->filterRules());

        if(Auth::user()->user_type == 'seller') {
            $seller_id = Auth::user()->id;
        } elseif($request->seller_id) {
            $seller_id = $request->seller_id;
        }

        $commission_history = CommissionHistory::orderBy('created_at', 'desc');

        if ($request->date_range) {
            $date_range = $request->date_range;
            $date_range1 = explode(" / ", $request->date_range);
            $commission_history = $commission_history->where('created_at', '>=', $date_range1[0]);
            $commission_history = $commission_history->where('created_at', '<=', $date_range1[1]);
        }
        if ($seller_id){

            $commission_history = $commission_history->where('seller_id', '=', $seller_id);
        }

        $commission_history = $commission_history->paginate(10);
        if(Auth::user()->user_type == 'seller') {
            return view('seller.reports.commission_history_report', compact('commission_history', 'seller_id', 'date_range'));
        }
        return view('shop.backend.reports.commission_history_report', compact('commission_history', 'seller_id', 'date_range'));
    }

    public function wallet_transaction_history(Request $request) {
        $user_id = null;
        $date_range = null;

        $request->validate($this->filterRules());

        if($request->user_id) {
            $user_id = $request->user_id;
        }

        $users_with_wallet = StoreUser::whereIn('id', function($query) {
            $query->select('user_id')->from(with(new Wallet)->getTable());
        })->get();

        $wallet_history = Wallet::orderBy('created_at', 'desc');

        if ($request->date_range) {
            $date_range = $request->date_range;
            $date_range1 = explode(" / ", $request->date_range);
            $wallet_history = $wallet_history->where('created_at', '>=', $date_range1[0]);
            $wallet_history = $wallet_history->where('created_at', '<=', $date_range1[1]);
        }
        if ($user_id){
            $wallet_history = $wallet_history->where('user_id', '=', $user_id);
        }

        $wallets = $wallet_history->paginate(10);

        return view('shop.backend.reports.wallet_history_report', compact('wallets', 'users_with_wallet', 'user_id', 'date_range'));
    }
}
