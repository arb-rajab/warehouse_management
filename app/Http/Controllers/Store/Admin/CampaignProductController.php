<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\SearchOptionsRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Support\SearchOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CampaignProductController extends Controller implements HasMiddleware
{
    use SearchesOptions;

    public static function middleware(): array
    {
        return [new Middleware('permission:add_offer_deal|edit_offer_deal|add_flash_deal|edit_flash_deal')];
    }

    /** The typeahead behind the flash deal and offer product pickers (StoreSearchSelect). */
    public function index(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $query = self::products()->where('published', 1)->where('approved', 1);
        if ($term !== '') {
            $this->whereTranslatedNameMatches($query, 'product_translations', $term);
        }
        $this->orderByRelevance($query, 'name', $term);

        return $this->optionsPage($query, fn (Product $product): array => SearchOption::campaignProduct($product));
    }

    public function brands(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $query = Brand::query()->without('brand_translations')->select(['id', 'name']);
        if ($term !== '') {
            $this->whereWordsMatch($query, ['name'], $term);
        }
        $this->orderByRelevance($query, 'name', $term);

        return $this->optionsPage($query, fn (Brand $brand): array => SearchOption::brand($brand));
    }

    /**
     * The columns and eager loads `SearchOption::campaignProduct()` reads.
     *
     * @return Builder<Product>
     */
    public static function products(): Builder
    {
        return Product::query()->select(['id', 'name', 'serial', 'unit_price', 'discount', 'discount_type', 'thumbnail_img'])->with([
            'product_translations' => fn ($q) => $q->select(['id', 'product_id', 'lang', 'name'])->where('lang', app()->getLocale()),
            'thumbnailUpload:id,file_name,external_link',
        ]);
    }
}
