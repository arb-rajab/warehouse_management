<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\FilterReportsRequest;
use App\Http\Requests\Store\Admin\SearchOptionsRequest;
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
use App\Support\NewestFirst;
use App\Support\SearchOption;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller implements HasMiddleware
{
    use SearchesOptions;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:in_house_product_sale_report', only: ['in_house_sale_report']),
            new Middleware('permission:seller_products_sale_report', only: ['seller_sale_report']),
            new Middleware('permission:products_stock_report', only: ['stock_report']),
            new Middleware('permission:product_wishlist_report', only: ['wish_report']),
            new Middleware('permission:user_search_report', only: ['user_search_report']),
            new Middleware('permission:commission_history_report', only: ['commission_history', 'commissionSellers']),
            new Middleware('permission:wallet_transaction_report', only: ['wallet_transaction_history', 'walletUsers']),
        ];
    }

    public function stock_report(FilterReportsRequest $request): Response
    {
        return $this->productReport($request, 'Stock', $this->products($request)->withSum('stocks as report_value', 'qty'));
    }

    public function in_house_sale_report(FilterReportsRequest $request): Response
    {
        return $this->productReport($request, 'InHouseSales', $this->products($request)->addSelect('num_of_sale as report_value')->where('added_by', 'admin')->orderByDesc('num_of_sale'));
    }

    public function wish_report(FilterReportsRequest $request): Response
    {
        return $this->productReport($request, 'Wishlist', $this->products($request)->withCount('wishlists as report_value'));
    }

    public function seller_sale_report(FilterReportsRequest $request): Response
    {
        $shops = Shop::query()->select(['id', 'user_id', 'name'])->with('user:id,name')
            ->addSelect([
                'report_sales' => Product::query()->selectRaw('sum(num_of_sale)')->whereColumn('products.user_id', 'shops.user_id'),
                'report_amount' => OrderDetail::query()->selectRaw('sum(price)')->whereColumn('order_details.seller_id', 'shops.user_id'),
            ])
            ->when($request->selected('verification_statuses'), fn (Builder $query, array $statuses) => $query->whereIn('verification_status', $statuses))
            ->tap(NewestFirst::apply(...));

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

        return $this->report($request, 'CommissionHistory', $rows->tap(NewestFirst::apply(...)), CommissionReportResource::class, [
            'seller_ids' => $request->selected('seller_ids'), ...$this->dateFilters($request),
        ], $this->selectedUsers($request->selected('seller_ids'), 'seller'));
    }

    /** The typeahead behind the commission report's seller filter (StoreSearchSelect). */
    public function commissionSellers(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $sellers = StoreUser::query()->select(['id', 'name', 'email'])->where('user_type', 'seller');
        if ($term !== '') {
            $this->whereWordsMatch($sellers, ['name', 'email'], $term);
        }
        $this->orderByRelevance($sellers, 'name', $term);

        return $this->optionsPage($sellers, fn (StoreUser $user): array => SearchOption::user($user));
    }

    /** The typeahead behind the wallet report's user filter (StoreSearchSelect): users with a wallet entry. */
    public function walletUsers(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $users = StoreUser::query()->select(['id', 'name', 'email'])->whereIn('id', Wallet::query()->select('user_id'));
        if ($term !== '') {
            $this->whereWordsMatch($users, ['name', 'email'], $term);
        }
        $this->orderByRelevance($users, 'name', $term);

        return $this->optionsPage($users, fn (StoreUser $user): array => SearchOption::user($user));
    }

    /**
     * The users already selected in a report's filter, as search options, so the
     * filter shows their names without listing every user.
     *
     * @param  array<int, string>|null  $ids
     * @return array<int, array<string, mixed>>
     */
    private function selectedUsers(?array $ids, ?string $type = null): array
    {
        if ($ids === null || $ids === []) {
            return [];
        }

        return StoreUser::query()->select(['id', 'name', 'email'])->whereIn('id', $ids)
            ->when($type !== null, fn (Builder $query) => $query->where('user_type', $type))
            ->orderBy('name')->orderBy('id')->get()->map(fn (StoreUser $user): array => SearchOption::user($user))->all();
    }

    public function wallet_transaction_history(FilterReportsRequest $request): Response
    {
        $rows = Wallet::query()->select(['id', 'user_id', 'amount', 'payment_method', 'created_at'])
            ->with('user:id,name')->when($request->selected('user_ids'), fn (Builder $query, array $ids) => $query->whereIn('user_id', $ids));
        $this->dateWindow($rows, $request);

        return $this->report($request, 'WalletHistory', $rows->tap(NewestFirst::apply(...)), WalletReportResource::class, [
            'user_ids' => $request->selected('user_ids'), ...$this->dateFilters($request),
        ], $this->selectedUsers($request->selected('user_ids')));
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
        return $this->report($request, $page, $query->tap(NewestFirst::apply(...)), ProductReportResource::class, [
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
     * @param  array<int, array<string, mixed>>  $options
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
