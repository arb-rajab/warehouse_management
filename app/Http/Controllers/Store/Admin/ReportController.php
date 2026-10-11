<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterReportsRequest;
use App\Http\Resources\Store\CommissionReportResource;
use App\Http\Resources\Store\ProductReportResource;
use App\Http\Resources\Store\SearchReportResource;
use App\Http\Resources\Store\SellerReportResource;
use App\Http\Resources\Store\WalletReportResource;
use App\Models\Category;
use App\Models\CommissionHistory;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Search;
use App\Models\Shop;
use App\Models\StoreUser;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
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

    public function stock_report(FilterReportsRequest $request): Response
    {
        return $this->productReport($request, 'Stock', $this->products($request)->withSum('stocks as report_value', 'qty')->orderByDesc('created_at'));
    }

    public function in_house_sale_report(FilterReportsRequest $request): Response
    {
        return $this->productReport($request, 'InHouseSales', $this->products($request)->addSelect('num_of_sale as report_value')->where('added_by', 'admin')->orderByDesc('num_of_sale'));
    }

    public function wish_report(FilterReportsRequest $request): Response
    {
        return $this->productReport($request, 'Wishlist', $this->products($request)->withCount('wishlists as report_value')->orderByDesc('created_at'));
    }

    public function seller_sale_report(FilterReportsRequest $request): Response
    {
        $shops = Shop::query()->select(['id', 'user_id', 'name'])->with('user:id,name')
            ->addSelect([
                'report_sales' => Product::query()->selectRaw('sum(num_of_sale)')->whereColumn('products.user_id', 'shops.user_id'),
                'report_amount' => OrderDetail::query()->selectRaw('sum(price)')->whereColumn('order_details.seller_id', 'shops.user_id'),
            ])
            ->when($request->selected('verification_statuses'), fn (Builder $query, array $statuses) => $query->whereIn('verification_status', $statuses))
            ->orderByDesc('created_at')->orderByDesc('id');

        return $this->report($request, 'SellerSales', $shops, SellerReportResource::class, [
            'verification_statuses' => $request->selected('verification_statuses'),
        ]);
    }

    public function user_search_report(FilterReportsRequest $request): Response
    {
        return $this->report($request, 'Searches', Search::query()->select(['id', 'query', 'count'])->orderByDesc('count')->orderByDesc('id'), SearchReportResource::class);
    }

    public function commission_history(FilterReportsRequest $request): Response
    {
        $rows = CommissionHistory::query()->select(['id', 'order_id', 'admin_commission', 'seller_earning', 'created_at'])
            ->with('order:id,code')->when($request->selected('seller_ids'), fn (Builder $query, array $ids) => $query->whereIn('seller_id', $ids));
        $this->dateWindow($rows, $request);

        return $this->report($request, 'CommissionHistory', $rows->orderByDesc('created_at')->orderByDesc('id'), CommissionReportResource::class, [
            'seller_ids' => $request->selected('seller_ids'), ...$this->dateFilters($request),
        ], StoreUser::query()->select(['id', 'name'])->where('user_type', 'seller')->orderBy('name')->get()
            ->map(fn (StoreUser $user) => ['value' => (string) $user->id, 'label' => $user->name])->all());
    }

    public function wallet_transaction_history(FilterReportsRequest $request): Response
    {
        $rows = Wallet::query()->select(['id', 'user_id', 'amount', 'payment_method', 'created_at'])
            ->with('user:id,name')->when($request->selected('user_ids'), fn (Builder $query, array $ids) => $query->whereIn('user_id', $ids));
        $this->dateWindow($rows, $request);

        return $this->report($request, 'WalletHistory', $rows->orderByDesc('created_at')->orderByDesc('id'), WalletReportResource::class, [
            'user_ids' => $request->selected('user_ids'), ...$this->dateFilters($request),
        ], StoreUser::query()->select(['id', 'name'])->whereIn('id', Wallet::query()->select('user_id'))->orderBy('name')->get()
            ->map(fn (StoreUser $user) => ['value' => (string) $user->id, 'label' => $user->name])->all());
    }

    /** @return Builder<Product> */
    private function products(FilterReportsRequest $request): Builder
    {
        return Product::query()->select(['id', 'name'])->with('product_translations:id,product_id,lang,name')
            ->when($request->selected('category_ids'), fn (Builder $query, array $ids) => $query->whereIn('category_id', $ids));
    }

    /** @param Builder<Product> $query */
    private function productReport(FilterReportsRequest $request, string $page, Builder $query): Response
    {
        return $this->report($request, $page, $query->orderByDesc('id'), ProductReportResource::class, [
            'category_ids' => $request->selected('category_ids'),
        ], Category::query()->select(['id', 'name'])->with('category_translations:id,category_id,lang,name')->orderBy('name')->get()
            ->map(fn (Category $category) => ['value' => (string) $category->id, 'label' => $category->getTranslation('name')])->all());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<JsonResource>  $resource
     * @param  array<string, mixed>  $filters
     * @param  array<int, array{value: string, label: string}>  $options
     */
    private function report(FilterReportsRequest $request, string $page, Builder $query, string $resource, array $filters = [], array $options = []): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Reports/'.$page, [
            'rows' => $this->paginated($resource::collection($query->paginate($perPage)->withQueryString())),
            'filters' => [...$filters, 'per_page' => $perPage],
            'options' => $options,
        ]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function dateWindow(Builder $query, FilterReportsRequest $request): void
    {
        if ($request->filled('created_within_days')) {
            $query->where('created_at', '>=', CarbonImmutable::now()->subDays($request->integer('created_within_days')));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', CarbonImmutable::parse($request->validated('date_from'))->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<', CarbonImmutable::parse($request->validated('date_to'))->addDay()->startOfDay());
        }
    }

    /** @return array<string, mixed> */
    private function dateFilters(FilterReportsRequest $request): array
    {
        return [
            'date_from' => $request->validated('date_from'),
            'date_to' => $request->validated('date_to'),
            'created_within_days' => $request->filled('created_within_days') ? $request->integer('created_within_days') : null,
        ];
    }
}
