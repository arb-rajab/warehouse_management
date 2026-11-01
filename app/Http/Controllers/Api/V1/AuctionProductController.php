<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuctionMiniCollection;
use App\Http\Resources\V1\AuctionProductDetailCollection;
use App\Http\Resources\V1\ProductMiniCollection;
use App\Models\Product;
use Request;


class AuctionProductController extends Controller
{

    public function index()
    {

        $products = Product::with(['taxes', 'product_translations'])->latest()->where('published', 1)->where('auction_product', 1);
        if (get_setting('seller_auction_product') == 0) {
            $products = $products->where('added_by', 'admin');
        }
        $products = $products->where('auction_start_date', '<=', strtotime("now"))->where('auction_end_date', '>=', strtotime("now"));


        return new AuctionMiniCollection($products->paginate(10));
    }


    public function details_auction_product(Request $request, $id)
    {
        $detailedProduct  = Product::where('id', $id)->get();
        return new AuctionProductDetailCollection($detailedProduct);
    }
}
