<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\FilterCouponRequest;
use App\Http\Requests\Store\Admin\SearchOptionsRequest;
use App\Http\Requests\Store\Admin\StoreCouponRequest;
use App\Http\Requests\Store\Admin\UpdateCouponRequest;
use App\Http\Resources\Store\CouponResource;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\StoreUser;
use App\Support\NewestFirst;
use App\Support\SearchOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller implements HasMiddleware
{
    use SearchesOptions;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_coupons', only: ['index']),
            new Middleware('permission:add_coupon', only: ['create', 'store']),
            new Middleware('permission:edit_coupon', only: ['edit', 'update']),
            new Middleware('permission:delete_coupon', only: ['destroy']),
            new Middleware('permission:add_coupon|edit_coupon', only: ['productOptions']),
        ];
    }

    public function index(FilterCouponRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Marketing/Coupons/Index', [
            'coupons' => $this->paginated(CouponResource::collection(Coupon::query()
                ->select(['id', 'type', 'code', 'details', 'discount', 'discount_type', 'start_date', 'end_date'])
                ->where('user_id', $this->ownerId())
                ->tap(NewestFirst::apply(...))
                ->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Marketing/Coupons/Create', ['selectedProducts' => []]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $ownerId = $this->ownerId();
        abort_if($ownerId === null, 409);
        Coupon::create($this->attributes($request->validated()) + ['user_id' => $ownerId]);

        return redirect()->route('coupon.index');
    }

    public function edit(Coupon $coupon): Response
    {
        $this->authorizeOwner($coupon);
        $details = json_decode($coupon->details, true) ?? [];
        $ids = $coupon->type === 'product_base' ? array_column($details, 'product_id') : [];

        return Inertia::render('Store/Admin/Marketing/Coupons/Edit', [
            'coupon' => new CouponResource($coupon),
            'selectedProducts' => $this->selectedOptions($ids),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->authorizeOwner($coupon);
        $coupon->update($this->attributes($request->validated()));

        return redirect()->route('coupon.index');
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorizeOwner($coupon);
        $coupon->delete();

        return $this->redirectPreservingQuery('coupon.index', $request);
    }

    /** The typeahead behind the coupon's product picker (StoreSearchSelect). */
    public function productOptions(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $query = $this->eligibleProducts();
        if ($term !== '') {
            $this->whereTranslatedNameMatches($query, 'product_translations', $term);
        }
        $this->orderByRelevance($query, 'name', $term);

        return $this->optionsPage($query, fn (Product $product): array => SearchOption::campaignProduct($product));
    }

    private function ownerId(): ?int
    {
        $id = StoreUser::query()->where('user_type', 'admin')->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function authorizeOwner(Coupon $coupon): void
    {
        abort_unless((int) $coupon->user_id === $this->ownerId(), 403);
    }

    /** @return Builder<Product> */
    private function eligibleProducts(): Builder
    {
        return filter_products(CampaignProductController::products()->where('user_id', $this->ownerId()));
    }

    /**
     * The coupon's own products as search options, in the order the coupon lists them.
     *
     * @param  array<int, mixed>  $ids
     * @return list<array<string, mixed>>
     */
    private function selectedOptions(array $ids): array
    {
        $order = array_flip(array_map('intval', $ids));

        return CampaignProductController::products()->whereIn('id', $ids)->get()
            ->sortBy(fn (Product $product): int => $order[$product->id] ?? PHP_INT_MAX)->values()
            ->map(fn (Product $product): array => SearchOption::campaignProduct($product))->all();
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $details = $validated['type'] === 'product_base'
            ? array_map(fn ($id) => ['product_id' => (int) $id], $validated['product_ids'])
            : ['min_buy' => (float) $validated['min_buy'], 'max_discount' => (float) $validated['max_discount']];

        return [
            'type' => $validated['type'],
            'code' => $validated['code'],
            'discount' => $validated['discount'],
            'discount_type' => $validated['discount_type'],
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'start_date' => strtotime($validated['start_date']),
            'end_date' => strtotime($validated['end_date']),
        ];
    }
}
