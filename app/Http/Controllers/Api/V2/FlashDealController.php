<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\FlashDealCollection;
use App\Http\Resources\V2\ProductCollection;
use App\Http\Resources\V2\ProductMiniCollection;
use App\Models\FlashDeal;

class FlashDealController extends Controller
{
    public function index()
    {
        $flash_deals = FlashDeal::where('status', 1)
            ->where('start_date', '<=', strtotime(date('d-m-Y')))
            ->where('end_date', '>=', strtotime(date('d-m-Y')))
            ->get();

        return new FlashDealCollection($flash_deals);
    }

    public function products($id)
    {
        $flash_deal = FlashDeal::find($id);
        $products = $flash_deal->flash_deal_products()->withListedProduct()->get()->pluck('product')->filter()->values();
        return new ProductMiniCollection($products);
    }
}
