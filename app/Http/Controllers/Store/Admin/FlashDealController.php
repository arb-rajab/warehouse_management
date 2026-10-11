<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditFlashDealRequest;
use App\Http\Requests\Store\Admin\FilterCampaignsRequest;
use App\Http\Requests\Store\Admin\StoreFlashDealRequest;
use App\Http\Requests\Store\Admin\ToggleCampaignRequest;
use App\Http\Requests\Store\Admin\UpdateFlashDealRequest;
use App\Http\Resources\Store\CampaignProductResource;
use App\Http\Resources\Store\FlashDealResource;
use App\Models\FlashDeal;
use App\Models\FlashDealProduct;
use App\Models\FlashDealTranslation;
use App\Models\Language;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FlashDealController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:view_all_flash_deals', only: ['index']), new Middleware('permission:add_flash_deal', only: ['create', 'store']), new Middleware('permission:edit_flash_deal', only: ['edit', 'update', 'update_status']), new Middleware('permission:publish_flash_deal', only: ['update_featured']), new Middleware('permission:delete_flash_deal', only: ['destroy'])];
    }

    public function index(FilterCampaignsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Marketing/FlashDeals/Index', ['deals' => $this->paginated(FlashDealResource::collection(FlashDeal::query()->without('flash_deal_translations')->select(['id', 'title', 'slug', 'banner', 'start_date', 'end_date', 'background_color', 'text_color', 'status', 'featured'])->when($search, fn ($q) => $q->where('title', 'like', '%'.$search.'%'))->orderByDesc('id')->paginate($perPage)->withQueryString())), 'filters' => ['search' => $search, 'per_page' => $perPage]]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Marketing/FlashDeals/Create', ['selectedProducts' => []]);
    }

    public function edit(EditFlashDealRequest $request, FlashDeal $flashDeal): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $products = CampaignProductController::products()->whereIn('id', FlashDealProduct::query()->where('flash_deal_id', $flashDeal->id)->pluck('product_id'))->get();

        return Inertia::render('Store/Admin/Marketing/FlashDeals/Edit', ['deal' => new FlashDealResource($flashDeal), 'title' => $flashDeal->getTranslation('title', $lang), 'lang' => $lang, 'languages' => Language::query()->select(['code', 'name'])->orderBy('id')->get(), 'products' => $products->map(fn (Product $p) => ['id' => $p->id, 'discount' => (float) $p->getAttribute('discount'), 'discount_type' => $p->getAttribute('discount_type') ?? 'amount'])->all(), 'selectedProducts' => CampaignProductResource::collection($products)]);
    }

    public function store(StoreFlashDealRequest $request): RedirectResponse
    {
        $this->saveDeal(new FlashDeal, $request->validated(), config('app.default_language'));

        return redirect()->route('flash_deals.index');
    }

    public function update(UpdateFlashDealRequest $request, FlashDeal $flashDeal): RedirectResponse
    {
        $this->saveDeal($flashDeal, $request->validated(), $request->validated('lang'));

        return redirect()->route('flash_deals.index');
    }

    /** @param array<string,mixed> $data */
    private function saveDeal(FlashDeal $deal, array $data, string $lang): void
    {
        DB::transaction(function () use ($deal, $data, $lang) {
            if (! $deal->exists || $lang === config('app.default_language')) {
                $deal->title = $data['title'];
                if (! $deal->slug) {
                    $deal->slug = Str::slug($data['title']).'-'.Str::random(5);
                }
            }
            $deal->banner = $data['banner'] ?? null;
            $deal->background_color = $data['background_color'];
            $deal->text_color = $data['text_color'];
            $deal->start_date = strtotime($data['start_date']);
            $deal->end_date = strtotime($data['end_date']);
            $deal->save();
            FlashDealProduct::query()->where('flash_deal_id', $deal->id)->delete();
            foreach ($data['products'] as $row) {
                FlashDealProduct::create(['flash_deal_id' => $deal->id, 'product_id' => $row['id']]);
                Product::query()->whereKey($row['id'])->update(['discount' => $row['discount'], 'discount_type' => $row['discount_type'], 'discount_start_date' => $deal->start_date, 'discount_end_date' => $deal->end_date]);
            }
            $translation = FlashDealTranslation::firstOrNew(['flash_deal_id' => $deal->id, 'lang' => $lang]);
            $translation->title = $data['title'];
            $translation->save();
        });
    }

    public function destroy(Request $request, FlashDeal $flashDeal): RedirectResponse
    {
        DB::transaction(function () use ($flashDeal) {
            $flashDeal->flash_deal_products()->delete();
            $flashDeal->flash_deal_translations()->delete();
            $flashDeal->delete();
        });

        return $this->redirectPreservingQuery('flash_deals.index', $request);
    }

    public function update_status(ToggleCampaignRequest $request, FlashDeal $flashDeal): RedirectResponse
    {
        $flashDeal->status = $request->boolean('value') ? 1 : 0;
        $flashDeal->save();

        return $this->redirectPreservingQuery('flash_deals.index', $request);
    }

    public function update_featured(ToggleCampaignRequest $request, FlashDeal $flashDeal): RedirectResponse
    {
        DB::transaction(function () use ($request, $flashDeal) {
            FlashDeal::query()->update(['featured' => 0]);
            $flashDeal->featured = $request->boolean('value') ? 1 : 0;
            $flashDeal->save();
        });

        return $this->redirectPreservingQuery('flash_deals.index', $request);
    }
}
