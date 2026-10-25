<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkDeleteDynamicPopupsRequest;
use App\Http\Requests\Store\Admin\FilterDynamicPopupsRequest;
use App\Http\Requests\Store\Admin\StoreDynamicPopupRequest;
use App\Http\Requests\Store\Admin\UpdateDynamicPopupRequest;
use App\Http\Requests\Store\Admin\UpdateDynamicPopupStatusRequest;
use App\Http\Resources\Store\DynamicPopupResource;
use App\Http\Resources\Store\DynamicPopupSummaryResource;
use App\Models\Category;
use App\Models\DynamicPopup;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Support\StorefrontUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DynamicPopupController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_dynamic_popups', only: ['index']),
            new Middleware('permission:add_dynamic_popups', only: ['create', 'store']),
            new Middleware('permission:edit_dynamic_popups', only: ['edit', 'update']),
            new Middleware('permission:delete_dynamic_popups', only: ['destroy', 'bulkDestroy']),
            new Middleware('permission:publish_dynamic_popups', only: ['updateStatus']),
        ];
    }

    public function index(FilterDynamicPopupsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Marketing/DynamicPopups/Index', [
            'popups' => $this->paginated(DynamicPopupSummaryResource::collection(
                DynamicPopup::query()->leftJoin('uploads', 'uploads.id', '=', 'dynamic_popups.banner')
                    ->select(['dynamic_popups.id', 'dynamic_popups.title', 'dynamic_popups.btn_link', 'dynamic_popups.status', 'uploads.id as banner_upload_id', 'uploads.file_name as banner_file_name', 'uploads.external_link as banner_external_link'])
                    ->when($search, fn (Builder $query) => $query->where('dynamic_popups.title', 'like', '%'.$search.'%'))
                    ->orderBy('dynamic_popups.id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage, 'search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Marketing/DynamicPopups/Create', $this->formOptions());
    }

    public function store(StoreDynamicPopupRequest $request): RedirectResponse
    {
        DynamicPopup::create($this->popupAttributes($request));

        return redirect()->route('dynamic-popups.index');
    }

    public function edit(DynamicPopup $popup): Response
    {
        return Inertia::render('Store/Admin/Marketing/DynamicPopups/Edit', [
            'popup' => new DynamicPopupResource($popup),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateDynamicPopupRequest $request, DynamicPopup $popup): RedirectResponse
    {
        $popup->update($this->popupAttributes($request));

        return redirect()->route('dynamic-popups.index');
    }

    public function destroy(Request $request, DynamicPopup $popup): RedirectResponse
    {
        $popup->delete();

        return $this->redirectPreservingQuery('dynamic-popups.index', $request);
    }

    public function bulkDestroy(BulkDeleteDynamicPopupsRequest $request): RedirectResponse
    {
        DynamicPopup::query()->whereIn('id', $request->validated('id'))->delete();

        return $this->redirectPreservingQuery('dynamic-popups.index', $request);
    }

    public function updateStatus(UpdateDynamicPopupStatusRequest $request): RedirectResponse
    {
        $popup = DynamicPopup::query()->findOrFail($request->integer('id'));
        $popup->status = (int) $request->boolean('status');
        $popup->save();

        return $this->redirectPreservingQuery('dynamic-popups.index', $request);
    }

    /** @return array<string, mixed> */
    private function popupAttributes(StoreDynamicPopupRequest|UpdateDynamicPopupRequest $request): array
    {
        $attributes = $request->validated();
        $product = OfferProduct::query()->join('products', 'products.id', '=', 'offer_products.product_id')
            ->where('offer_products.offer_id', $attributes['offer_id'])->where('is_base_product', 1)
            ->first(['products.id', 'products.slug']);
        if ($product === null || (is_string($product->getAttribute('slug')) && $product->getAttribute('slug') !== '' && trim($product->getAttribute('slug')) === '')) {
            throw ValidationException::withMessages(['offer_id' => __('validation.exists', ['attribute' => 'offer_id'])]);
        }

        return [
            ...$attributes,
            'btn_link' => StorefrontUrl::forRow('product', $product->getAttribute('slug'), (int) $product->getAttribute('id')),
            'btn_background_color' => '#000000',
            'btn_text_color' => 'white',
        ];
    }

    /** @return array{offers: array<mixed>, categories: array<mixed>} */
    private function formOptions(): array
    {
        return [
            'offers' => Offer::query()->select(['id', 'title'])
                ->whereExists(fn (QueryBuilder $query) => $query->selectRaw('1')->from('offer_products')
                    ->join('products', 'products.id', '=', 'offer_products.product_id')
                    ->whereColumn('offer_products.offer_id', 'offers.id')->where('is_base_product', 1)
                    ->where(fn (QueryBuilder $slugs) => $slugs->whereNull('products.slug')->orWhereRaw('LENGTH(products.slug) = 0')->orWhereRaw("TRIM(products.slug) <> ''")))
                ->orderBy('title')->orderBy('id')->get()
                ->map(fn (Offer $offer) => ['id' => $offer->id, 'name' => $offer->title])->all(),
            'categories' => Category::query()->without('category_translations')->select(['categories.id'])
                ->selectRaw('COALESCE(category_translations.name, categories.name) as display_name')
                ->leftJoin('category_translations', fn (JoinClause $join) => $join->on('category_translations.category_id', '=', 'categories.id')->where('category_translations.lang', app()->getLocale()))
                ->orderBy('categories.name')->orderBy('categories.id')->get()
                ->map(fn (Category $category) => ['id' => $category->id, 'name' => (string) $category->getAttribute('display_name')])->all(),
        ];
    }
}
