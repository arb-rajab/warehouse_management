<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function index(Request $request)
    {
        $sort_search = null;
        $offer = Offer::orderBy('created_at', 'desc');
        if ($request->has('search')){
            $sort_search = $request->search;
            $offer = $offer->where('title', 'like', '%'.$sort_search.'%');
        }
        $offer = $offer->paginate(15);
        return view('backend.marketing.offer_deals.index', compact('offer', 'sort_search'));
    }

    public function create()
    {
        return view('backend.marketing.offer_deals.create');
    }

    public function show($id)
    {
        //
    }

    public function edit(Request $request, $id)
    {
        $offer = Offer::findOrFail($id);
        if($offer->offer_type == 'brand_offer'){
            return view('backend.marketing.offer_deals.brands.edit', compact('offer'));
        }else{
            return view('backend.marketing.offer_deals.edit', compact('offer'));
        }
    }

    public function store(Request $request)
    {
        if($request->has('brand_offer')){
            $result = $this->store_brand_offer($request);
            if($result){
                flash(translate('Offer Deal has been inserted successfully'))->success();
                return redirect()->route('offer_deals.index');
            }
            else{
                flash(translate('Something went wrong'))->error();
                return back();
            }
        }else{

            $result = $this->store_product_offer($request);
            if($result){
                flash(translate('Offer Deal has been inserted successfully'))->success();
                return redirect()->route('offer_deals.index');
            }
            else{
                flash(translate('Something went wrong'))->error();
                return back();
            }
        }
    }

    public function update(Request $request, $id)
    {
        if($request->has('brand_offer')){
            $result = $this->update_brand_offer($request, $id);
            if($result){
            
                flash(translate('Offer Deal has been updated successfully'))->success();
                return redirect()->route('offer_deals.index');
            }
            else{
                flash(translate('Something went wrong'))->error();
                return back();
            }
        }else{
            $result = $this->update_product_offer($request, $id);
            if($result){
                flash(translate('Offer Deal has been updated successfully'))->success();
                return redirect()->route('offer_deals.index');
            }
            else{
                flash(translate('Something went wrong'))->error();
                return back();
            }
        }
    }

    public function destroy($id)
    {
        $offer = Offer::findOrFail($id);

        $offer->base_product()->delete();
        $offer->offer_products()->delete();
        $offer->users()->detach($offer->id);

        Offer::destroy($id);
        flash(translate('Offer has been deleted successfully'))->success();
        return redirect()->route('offer_deals.index');
    }

    public function update_repeatable(Request $request)
    {
        $offer = Offer::findOrFail($request->id);
        $offer->is_repeatable = $request->repeatable;
        if($offer->save()){
            flash(translate('Offer status updated successfully'))->success();
            return 1;
        }
        return 0;
    }

    public function update_status(Request $request)
    {
        $offer = Offer::findOrFail($request->id);
        $offer->is_active = $request->status;
        if($offer->save()){
            flash(translate('Offer status updated successfully'))->success();
            return 1;
        }
        return 0;
    }
    public function update_multiple_usage(Request $request)
    {
        $offer = Offer::findOrFail($request->id);
        $offer->multiple_usage = $request->multiple_usage;
        if($offer->save()){
            flash(translate('Offer status updated successfully'))->success();
            return 1;
        }
        return 0;
    }

    public function product_discount(Request $request){
        $product_ids = $request->product_ids;
        return view('backend.marketing.offer_deals.offer_deal_discount', compact('product_ids'));
    }
    public function base_product_discount(Request $request){
        $product_id = $request->product_id;
        return view('backend.marketing.offer_deals.base_offer_deal_discount', compact('product_id'));
    }

    public function product_discount_edit(Request $request){
        $product_ids = $request->product_ids;
        $base_product_id = $request->base_product_id;
        $offer_deal_id = $request->offer_id;
        return view('backend.marketing.offer_deals.offer_deal_discount_edit', compact('product_ids', 'base_product_id', 'offer_deal_id'));
    }

    public function base_brand_discount(Request $request){
        $brand_id = $request->brand_id;
        return view('backend.marketing.offer_deals.brands.base_offer_deal_discount', compact('brand_id'));
    }

    public function brand_discount_edit(Request $request){
        $product_ids = $request->product_ids;
        $brand = $request->brand;
        $offer_deal_id = $request->offer_id;
        return view('backend.marketing.offer_deals.brands.offer_deal_discount_edit', compact('product_ids', 'brand', 'offer_deal_id'));
    }

    public function brandCreate()
    {
        return view('backend.marketing.offer_deals.brands.create');
    }

    protected function store_brand_offer($request)
    {
        $brand_product = Product::where('brand_id', $request->brand)->where('published', 1)->where('approved', 1)->first();

        $offer = new Offer;
        $offer->title = $request->title;
        $offer->offer_type = 'brand_offer';
        $offer->brand_id = $request->brand;

        $date_var = explode(" to ", $request->date_range);
        $offer->start_date = strtotime($date_var[0]);
        $offer->end_date   = strtotime( $date_var[1]);

        $offer->banner = $request->banner;
        if($offer->save()){
            foreach ($request->products as $key => $product) {
                $offer_product = new OfferProduct;
                $offer_product->offer_id = $offer->id;
                $offer_product->product_id = $product;
                $offer_product->is_base_product = 0;
                $offer_product->quantity = $request['discount_quantity_'.$product];
                $offer_product->discount = $request['discount_'.$product];
                $offer_product->discount_type = $request['discount_type_'.$product];
                $offer_product->save();
            }
            // foreach($brand_products as $brand_product){
                $offer_product = new OfferProduct;
                $offer_product->offer_id = $offer->id;
                $offer_product->product_id = $brand_product->id;
                $offer_product->is_base_product = 1;
                $offer_product->quantity = $request['brand_amount_required'];
                $offer_product->save();
            // }
            return 1;
        }else{
            return 0;
        }
    }
    protected function store_product_offer($request)
    {
        $offer = new Offer;
        $offer->title = $request->title;

        $date_var = explode(" to ", $request->date_range);
        $offer->start_date = strtotime($date_var[0]);
        $offer->end_date   = strtotime( $date_var[1]);

        $offer->banner = $request->banner;
        if($offer->save()){
            foreach ($request->products as $key => $product) {
                $offer_product = new OfferProduct;
                $offer_product->offer_id = $offer->id;
                $offer_product->product_id = $product;
                $offer_product->is_base_product = 0;
                $offer_product->quantity = $request['discount_quantity_'.$product];
                $offer_product->discount = $request['discount_'.$product];
                $offer_product->discount_type = $request['discount_type_'.$product];
                $offer_product->save();
            }
            $offer_product = new OfferProduct;
            $offer_product->offer_id = $offer->id;
            $offer_product->product_id = $request->base_product;
            $offer_product->is_base_product = 1;
            $offer_product->quantity = $request['base_quantity_required'];
            $offer_product->save();

            return 1;
        }else{
            return 0;
        }
    }
    protected function update_brand_offer($request, $id)
    {
        $offer = Offer::findOrFail($id);
        $brand_product = Product::where('brand_id', $request->brand)->where('published', 1)->where('approved', 1)->first();

        $offer->title = $request->title;

        $date_var               = explode(" to ", $request->date_range);
        $offer->start_date = strtotime($date_var[0]);
        $offer->end_date   = strtotime( $date_var[1]);

        $offer->banner = $request->banner;

        // delete offer_products
        foreach ($offer->offer_products as $key => $offer_product) {
            $offer_product->delete();
        }
        // delete offer_base_product
        $offer->base_product->delete();


        if($offer->save()){
            foreach ($request->products as $key => $product) {
                $offer_product = new OfferProduct;
                $offer_product->offer_id = $offer->id;
                $offer_product->product_id = $product;
                $offer_product->is_base_product = 0;
                $offer_product->quantity = $request['discount_quantity_'.$product];
                $offer_product->discount = $request['discount_'.$product];
                $offer_product->discount_type = $request['discount_type_'.$product];
                $offer_product->save();
            }
            $offer_product = new OfferProduct;
            $offer_product->offer_id = $offer->id;
            $offer_product->product_id = $brand_product->id;
            $offer_product->is_base_product = 1;
            $offer_product->quantity = $request['brand_amount_required'];
            $offer_product->save();

            return 1;
        }else{
            return 0;
        }
    }

    protected function update_product_offer($request, $id)
    {
        $offer = Offer::findOrFail($id);

        $offer->title = $request->title;

        $date_var               = explode(" to ", $request->date_range);
        $offer->start_date = strtotime($date_var[0]);
        $offer->end_date   = strtotime( $date_var[1]);

        $offer->banner = $request->banner;

        // delete offer_products
        foreach ($offer->offer_products as $key => $offer_product) {
            $offer_product->delete();
        }
        // delete offer_base_product
        $offer->base_product->delete();


        if($offer->save()){
            foreach ($request->products as $key => $product) {
                $offer_product = new OfferProduct;
                $offer_product->offer_id = $offer->id;
                $offer_product->product_id = $product;
                $offer_product->is_base_product = 0;
                $offer_product->quantity = $request['discount_quantity_'.$product];
                $offer_product->discount = $request['discount_'.$product];
                $offer_product->discount_type = $request['discount_type_'.$product];
                $offer_product->save();
            }
            $offer_product = new OfferProduct;
            $offer_product->offer_id = $offer->id;
            $offer_product->product_id = $request->base_product;
            $offer_product->is_base_product = 1;
            $offer_product->quantity = $request['base_quantity_required'];
            $offer_product->save();

            return 1;
        }else{
            return 0;
        }
    }
}
