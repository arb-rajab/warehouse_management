<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreBrandLogos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterBrandDiscountsRequest;
use App\Http\Requests\Store\Admin\UpdateBrandDiscountRequest;
use App\Http\Resources\Store\BrandResource;
use App\Models\Brand;
use App\Models\StoreUser;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BrandDiscountController extends Controller implements HasMiddleware
{
    use ProvidesStoreBrandLogos;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_brands', only: ['index']),
            new Middleware('permission:product_edit', only: ['update']),
        ];
    }

    public function index(FilterBrandDiscountsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');
        $brands = Brand::query()->select(['id', 'name', 'logo'])
            ->with('brand_translations:id,brand_id,name,lang')
            ->when($search !== null && $search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('id')->paginate($perPage)->withQueryString();
        $this->attachBrandLogoUrls($brands->getCollection());

        return Inertia::render('Store/Admin/Product/BrandDiscounts/Index', [
            'brands' => $this->paginated(BrandResource::collection($brands)),
            'filters' => ['per_page' => $perPage, 'search' => $search],
        ]);
    }

    public function update(UpdateBrandDiscountRequest $request, ProductService $products): RedirectResponse
    {
        if (! StoreUser::query()->where('user_type', 'admin')->exists()) {
            throw ValidationException::withMessages(['discount' => __('messages.store_brand_discount_admin_required')]);
        }
        $products->setBrandWiseDiscount([
            ...$request->validated(),
            'date_range' => $request->validated('date_range'),
            'seller_product_discount' => $request->boolean('seller_product_discount'),
        ]);

        return $this->redirectPreservingQuery('brands_wise_product_discount', $request);
    }
}
