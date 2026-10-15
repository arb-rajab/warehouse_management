<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\SearchCampaignProductsRequest;
use App\Http\Resources\Store\CampaignProductResource;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CampaignProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:add_offer_deal|edit_offer_deal|add_flash_deal|edit_flash_deal')];
    }

    public function index(SearchCampaignProductsRequest $request): JsonResponse
    {
        $q = $request->validated('q');
        $query = self::products()->where('published', 1)->where('approved', 1);
        if ($q) {
            $query->where(fn (Builder $query) => $query->where('name', 'like', '%'.$q.'%')->orWhereHas('product_translations', fn (Builder $query) => $query->where('lang', app()->getLocale())->where('name', 'like', '%'.$q.'%')));
        }

        return response()->json(CampaignProductResource::collection($query->orderBy('name')->orderBy('id')->limit(30)->get()));
    }

    public function brands(SearchCampaignProductsRequest $request): JsonResponse
    {
        return response()->json(Brand::query()->without('brand_translations')->select(['id', 'name'])->when($request->validated('q'), fn (Builder $q, $search) => $q->where('name', 'like', '%'.$search.'%'))->orderBy('name')->orderBy('id')->limit(30)->get());
    }

    /** @return Builder<Product> */
    public static function products(): Builder
    {
        return Product::query()->select(['id', 'name', 'unit_price', 'discount', 'discount_type'])->with(['product_translations' => fn ($q) => $q->select(['id', 'product_id', 'lang', 'name'])->where('lang', app()->getLocale())]);
    }
}
