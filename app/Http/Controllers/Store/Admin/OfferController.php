<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterCampaignsRequest;
use App\Http\Requests\Store\Admin\StoreOfferRequest;
use App\Http\Requests\Store\Admin\ToggleCampaignRequest;
use App\Http\Requests\Store\Admin\UpdateOfferRequest;
use App\Http\Resources\Store\CampaignProductResource;
use App\Http\Resources\Store\OfferResource;
use App\Models\Brand;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:view_all_offer_deals', only: ['index']), new Middleware('permission:add_offer_deal', only: ['create', 'brandCreate', 'store']), new Middleware('permission:edit_offer_deal', only: ['edit', 'update', 'update_status', 'update_repeatable', 'update_multiple_usage']), new Middleware('permission:delete_offer_deal', only: ['destroy'])];
    }

    public function index(FilterCampaignsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Marketing/Offers/Index', ['offers' => $this->paginated(OfferResource::collection(Offer::query()->select(['id', 'title', 'offer_type', 'banner', 'brand_id', 'start_date', 'end_date', 'is_active', 'is_repeatable', 'multiple_usage'])->when($search, fn ($q) => $q->where('title', 'like', '%'.$search.'%'))->orderByDesc('id')->paginate($perPage)->withQueryString())), 'filters' => ['search' => $search, 'per_page' => $perPage]]);
    }

    public function create(): Response
    {
        return $this->createForm('products_offer');
    }

    public function brandCreate(): Response
    {
        return $this->createForm('brand_offer');
    }

    private function createForm(string $type): Response
    {
        return Inertia::render('Store/Admin/Marketing/Offers/Create', ['offerType' => $type, 'selectedProducts' => [], 'selectedBrand' => null]);
    }

    public function edit(Offer $offer): Response
    {
        $rows = OfferProduct::query()->select(['product_id', 'is_base_product', 'quantity', 'discount', 'discount_type'])->where('offer_id', $offer->id)->orderBy('id')->get();
        $base = $rows->firstWhere('is_base_product', 1);

        return Inertia::render('Store/Admin/Marketing/Offers/Edit', ['offer' => new OfferResource($offer), 'baseProductId' => $base?->product_id, 'baseQuantity' => (float) ($base->quantity ?? 1), 'products' => $rows->where('is_base_product', 0)->values()->map(fn ($row) => ['id' => (int) $row->product_id, 'quantity' => (int) $row->quantity, 'discount' => (float) $row->discount, 'discount_type' => $row->discount_type ?? 'amount'])->all(), 'selectedProducts' => CampaignProductResource::collection(CampaignProductController::products()->whereIn('id', $rows->pluck('product_id'))->get()), 'selectedBrand' => $offer->brand_id ? Brand::query()->without('brand_translations')->select(['id', 'name'])->find($offer->brand_id) : null]);
    }

    public function store(StoreOfferRequest $request): RedirectResponse
    {
        $this->saveOffer(new Offer, $request->validated());

        return redirect()->route('offer_deals.index');
    }

    public function update(UpdateOfferRequest $request, Offer $offer): RedirectResponse
    {
        $this->saveOffer($offer, $request->validated());

        return redirect()->route('offer_deals.index');
    }

    /** @param array<string,mixed> $data */
    private function saveOffer(Offer $offer, array $data): void
    {
        DB::transaction(function () use ($offer, $data) {
            $baseId = $data['offer_type'] === 'brand_offer' ? Product::query()->where('brand_id', $data['brand_id'])->where('published', 1)->where('approved', 1)->orderBy('id')->value('id') : $data['base_product_id'];
            $offer->title = $data['title'];
            $offer->offer_type = $data['offer_type'];
            $offer->brand_id = $data['offer_type'] === 'brand_offer' ? $data['brand_id'] : null;
            $offer->banner = $data['banner'] ?? null;
            $offer->start_date = strtotime($data['start_date']);
            $offer->end_date = strtotime($data['end_date']);
            $offer->save();
            OfferProduct::query()->where('offer_id', $offer->id)->delete();
            foreach ($data['products'] as $row) {
                $product = new OfferProduct;
                $product->offer_id = $offer->id;
                $product->product_id = $row['id'];
                $product->is_base_product = false;
                $product->quantity = $row['quantity'];
                $product->discount = $row['discount'];
                $product->discount_type = $row['discount_type'];
                $product->save();
            }
            $base = new OfferProduct;
            $base->offer_id = $offer->id;
            $base->product_id = $baseId;
            $base->is_base_product = true;
            $base->quantity = $data['base_quantity'];
            $base->save();
        });
    }

    public function destroy(Request $request, Offer $offer): RedirectResponse
    {
        DB::transaction(function () use ($offer) {
            OfferProduct::query()->where('offer_id', $offer->id)->delete();
            $offer->users()->detach();
            $offer->delete();
        });

        return $this->redirectPreservingQuery('offer_deals.index', $request);
    }

    public function update_status(ToggleCampaignRequest $request, Offer $offer): RedirectResponse
    {
        return $this->toggle($request, $offer, 'is_active');
    }

    public function update_repeatable(ToggleCampaignRequest $request, Offer $offer): RedirectResponse
    {
        return $this->toggle($request, $offer, 'is_repeatable');
    }

    public function update_multiple_usage(ToggleCampaignRequest $request, Offer $offer): RedirectResponse
    {
        return $this->toggle($request, $offer, 'multiple_usage');
    }

    private function toggle(ToggleCampaignRequest $request, Offer $offer, string $field): RedirectResponse
    {
        $offer->$field = $request->boolean('value');
        $offer->save();

        return $this->redirectPreservingQuery('offer_deals.index', $request);
    }
}
