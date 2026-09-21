<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CellState;
use App\Http\Controllers\Concerns\BuildsCellLogFilterOptions;
use App\Http\Controllers\Concerns\BuildsProductQrLabels;
use App\Http\Controllers\Concerns\ExpiringSoonDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterProductsRequest;
use App\Http\Requests\Admin\SearchProductsRequest;
use App\Http\Requests\Admin\UpdateProductBoxCountRequest;
use App\Http\Resources\ProductOptionResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use BuildsCellLogFilterOptions, BuildsProductQrLabels;

    /**
     * @var list<string>
     */
    private const array SORTABLE_COLUMNS = [
        'name', 'full_cells_count', 'opened_cells_count', 'expired_cells_count',
        'expiring_soon_count', 'activity_today_count', 'activity_week_count',
    ];

    public function index(FilterProductsRequest $request): Response
    {
        $today = today();
        $weekStart = $today->copy()->startOfWeek();
        $expiringSoonDays = $request->filled('expires_within_days')
            ? $request->integer('expires_within_days')
            : ExpiringSoonDefaults::CUSTOM_WINDOW_DAYS;

        $historyFiltersActive = $request->filled('user_id')
            || $request->filled('action')
            || $request->filled('date_from')
            || $request->filled('date_to')
            || $request->filled('created_within_days');

        $direction = $request->string('sort_direction')->value() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($request->string('sort_by')->value(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort_by')->value()
            : 'name';
        $perPage = $this->resolvePerPage($request, 20);

        $products = Product::query()
            ->select(['id', 'name', 'ar_name', 'thumbnail_img', 'published'])
            ->with(Product::WITH_DERIVED_ATTRIBUTES)
            ->addSelect(['full_cells_count' => $this->occupancyCountSubquery($request, CellState::Full)])
            ->addSelect(['opened_cells_count' => $this->occupancyCountSubquery($request, CellState::Opened)])
            ->addSelect(['expired_cells_count' => $this->occupancyCountSubquery(
                $request,
                null,
                fn (Builder $query) => $query->where('expiration_date', '<', $today),
            )])
            ->addSelect(['expiring_soon_count' => $this->occupancyCountSubquery(
                $request,
                null,
                fn (Builder $query) => $query->where('expiration_date', '<=', $today->copy()->addDays($expiringSoonDays)),
            )])
            ->addSelect(['activity_today_count' => $this->activityCountSubquery($request, $today->copy()->startOfDay(), $today->copy()->endOfDay())])
            ->addSelect(['activity_week_count' => $this->activityCountSubquery($request, $weekStart->copy()->startOfDay(), $today->copy()->endOfDay())])
            ->when($request->filled('product_id'), fn (Builder $query) => $query->whereIn('id', $request->productIds()))
            ->when($request->boolean('inactive'), fn (Builder $query) => $query->where('published', false))
            ->when($historyFiltersActive, function (Builder $query) use ($request) {
                $existsSubquery = CellStatusLog::query()->whereColumn('cell_status_logs.product_id', 'products.id');
                $this->applyHistoryLogFilters($existsSubquery, $request);

                $query->whereExists($existsSubquery);
            })
            ->orderBy($sortBy, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $this->paginated(ProductSummaryResource::collection($products)),
            'today' => $today->toDateString(),
            'weekStart' => $weekStart->toDateString(),
            'expiringSoonDays' => $expiringSoonDays,
            'filters' => [
                ...$request->only([
                    'row_id', 'column_number', 'state', 'expired', 'expires_within_days', 'inactive', 'product_id',
                    'user_id', 'action', 'date_from', 'date_to', 'created_within_days',
                    'sort_by', 'sort_direction',
                ]),
                // The resolved value (not the raw request), so the per-page
                // selector always reflects the actual page size, including
                // the default when no `per_page` param was sent.
                'per_page' => $perPage,
            ],
            'filterOptions' => $this->productRowUserActionFilterOptions($request->productIds()),
        ]);
    }

    /**
     * Sets how many boxes a full pallet of this product holds.
     *
     * The product itself belongs to the store app and is never written here —
     * this is WMS-owned data in `wms_product_settings`, which the store has no
     * column for. `updateOrCreate` because a product the store added may have
     * no settings row yet. See .ai/rules/shared-database.md.
     *
     * Redirects to `admin.products.index` rather than `back()` — the Referer
     * header is stripped app-wide by `no-referrer` policy, and Inertia SPA
     * visits don't update the session's previous URL, so `back()` lands on
     * whatever the last full page load was (typically the dashboard).
     */
    public function updateBoxCount(UpdateProductBoxCountRequest $request, Product $product): RedirectResponse
    {
        $product->setting()->updateOrCreate([], [
            'boxes_count' => $request->integer('boxes_count'),
        ]);

        return redirect()->route('admin.products.index', $request->query());
    }

    /**
     * @return array<string, mixed>
     */
    public function search(SearchProductsRequest $request): array
    {
        return $this->paginated(ProductOptionResource::collection(
            Product::query()
                ->select(['id', 'name', 'ar_name'])
                ->where('published', true)
                ->searchByName($request->string('q')->value())
                ->orderBy('name')
                ->paginate(20)
        ));
    }

    /**
     * One QR per product id, meant to be printed and stuck on every
     * box/pallet of that product — see BuildsProductQrLabels.
     */
    public function exportQr(Product $product): HttpResponse
    {
        return response($this->productQrLabelImage($product))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"product-{$product->id}-qr.svg\"");
    }

    /**
     * A correlated count of the currently-occupied cells matching the
     * occupancy filters (row/column/state/expiration) for the product on
     * each outer row — added as a scalar subquery select, the same idiom
     * `CellStatusLog::sorted()` uses to sort by a related pallet's column.
     *
     * `$forcedState` fixes the cell state a particular column counts
     * (full/opened); `$extraPalletConstraint`, when given, adds a further
     * condition on the pallet itself (used for the expired/expiring-soon
     * columns, which aren't tied to one cell state).
     *
     * @return Builder<Pallet>
     */
    private function occupancyCountSubquery(Request $request, ?CellState $forcedState, ?callable $extraPalletConstraint = null): Builder
    {
        return Pallet::query()
            ->selectRaw('count(*)')
            ->whereColumn('pallets.product_id', 'products.id')
            ->when($request->filled('expired'), fn (Builder $query) => $query->where('expiration_date', '<', today()))
            ->when($request->filled('expires_within_days'), fn (Builder $query) => $query->where('expiration_date', '<=', now()->addDays($request->integer('expires_within_days'))))
            ->when($extraPalletConstraint !== null, fn (Builder $query) => $extraPalletConstraint($query))
            ->whereHas('cell', function (Builder $cellQuery) use ($request, $forcedState) {
                $this->applyOccupancyCellFilters($cellQuery, $request);

                if ($forcedState !== null) {
                    $cellQuery->where('state', $forcedState->value);
                }
            });
    }

    /**
     * A correlated count of `cell_status_logs` rows within the given date
     * window, matching the history filters (row/column/user/action/date
     * range) for the product on each outer row.
     *
     * @return Builder<CellStatusLog>
     */
    private function activityCountSubquery(Request $request, CarbonInterface $from, CarbonInterface $to): Builder
    {
        $query = CellStatusLog::query()
            ->selectRaw('count(*)')
            ->whereColumn('cell_status_logs.product_id', 'products.id')
            ->whereBetween('created_at', [$from, $to]);

        $this->applyHistoryLogFilters($query, $request);

        return $query;
    }

    /**
     * Filters a cells-table query by row/column/state. Deliberately typed as
     * `Builder<Model>` rather than `Builder<Cell>`: both call sites reach
     * this via a `whereHas('cell', ...)` relation closure, and Larastan
     * cannot trace the relation's target model through a query builder that
     * arrives as a function parameter rather than a direct `Model::query()`
     * call, so it always narrows that closure's builder to the bare `Model`
     * generic.
     *
     * @param  Builder<Model>  $query
     */
    private function applyOccupancyCellFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('row_id'), fn (Builder $q) => $q->where('row_id', $request->integer('row_id')))
            ->when($request->filled('column_number'), fn (Builder $q) => $q->where('cell_number', $request->integer('column_number')))
            ->when($request->filled('state'), fn (Builder $q) => $q->where('state', $request->string('state')->value()));
    }

    /**
     * @param  Builder<CellStatusLog>  $query
     */
    private function applyHistoryLogFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('user_id'), fn (Builder $q) => $q->whereIn('user_id', array_map('intval', $request->array('user_id'))))
            ->when($request->filled('action'), fn (Builder $q) => $q->whereIn('action', CellStatusLog::actionValuesFromRequest($request)))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('created_within_days'), fn (Builder $q) => $q->whereDate('created_at', '>=', now()->subDays($request->integer('created_within_days'))))
            ->when(
                $request->filled('row_id') || $request->filled('column_number') || $request->filled('state'),
                fn (Builder $q) => $q->whereHas('cell', function (Builder $cellQuery) use ($request) {
                    $this->applyOccupancyCellFilters($cellQuery, $request);
                }),
            );
    }
}
