<?php

namespace App\Http\Controllers\Seller;

use App\Services\ShopVerificationStorage;
use App\Http\Requests\Seller\UpdateShopRequest;
use App\Http\Requests\Seller\StoreShopVerificationRequest;
use App\Support\ShopVerificationForm;
use App\Models\Shop;
use Auth;

class ShopController extends Controller
{
    public function index()
    {
        $shop = Auth::user()->shop;
        return view('shop.seller.shop', compact('shop'));
    }

    public function update(UpdateShopRequest $request)
    {
        $shop = Auth::user()->shop ?? abort(404);

        if ($request->has('name') && $request->has('address')) {
            if ($request->has('shipping_cost')) {
                $shop->shipping_cost = $request->shipping_cost;
            }

            $shop->name             = $request->name;
            $shop->address          = $request->address;
            $shop->phone            = $request->phone;
            $shop->slug             = preg_replace('/\s+/', '-', $request->name) . '-' . $shop->id;
            $shop->meta_title       = $request->meta_title;
            $shop->meta_description = $request->meta_description;
            $shop->logo             = $request->logo;
        }

        if ($request->has('delivery_pickup_longitude') && $request->has('delivery_pickup_latitude')) {

            $shop->delivery_pickup_longitude    = $request->delivery_pickup_longitude;
            $shop->delivery_pickup_latitude     = $request->delivery_pickup_latitude;
        } elseif (
            $request->has('facebook') ||
            $request->has('google') ||
            $request->has('twitter') ||
            $request->has('youtube') ||
            $request->has('instagram')
        ) {
            $shop->facebook = $request->facebook;
            $shop->instagram = $request->instagram;
            $shop->google = $request->google;
            $shop->twitter = $request->twitter;
            $shop->youtube = $request->youtube;
        } elseif (
            $request->has('top_banner') ||
            $request->has('sliders') ||
            $request->has('banner_full_width_1') ||
            $request->has('banners_half_width') ||
            $request->has('banner_full_width_2')
        ) {
            $shop->top_banner = $request->top_banner;
            $shop->sliders = $request->sliders;
            $shop->banner_full_width_1 = $request->banner_full_width_1;
            $shop->banners_half_width = $request->banners_half_width;
            $shop->banner_full_width_2 = $request->banner_full_width_2;
        }

        if ($shop->save()) {
            flash(translate('Your Shop has been updated successfully!'))->success();
            return back();
        }

        flash(translate('Sorry! Something went wrong.'))->error();
        return back();
    }

    public function verify_form ()
    {
        if (Auth::user()->shop->verification_info == null) {
            $shop = Auth::user()->shop;
            return view('shop.seller.verify_form', compact('shop'));
        } else {
            flash(translate('Sorry! You have sent verification request already.'))->error();
            return back();
        }
    }

    public function verify_form_store(StoreShopVerificationRequest $request)
    {
        $data = [];
        $i = 0;
        foreach (ShopVerificationForm::fields() as $key => $element) {
            $item = [];
            if ($element->type == 'text') {
                $item['type'] = 'text';
                $item['label'] = $element->label;
                $item['value'] = $request['element_' . $i];
            } elseif ($element->type == 'select' || $element->type == 'radio') {
                $item['type'] = 'select';
                $item['label'] = $element->label;
                $item['value'] = $request['element_' . $i];
            } elseif ($element->type == 'multi_select') {
                $item['type'] = 'multi_select';
                $item['label'] = $element->label;
                $item['value'] = json_encode($request['element_' . $i]);
            } elseif ($element->type == 'file') {
                $item['type'] = 'file';
                $item['label'] = $element->label;
                $file = $request->file('element_' . $i);
                $item['value'] = $file ? app(ShopVerificationStorage::class)->store($file) : null;
            }
            array_push($data, $item);
            $i++;
        }
        $shop = Auth::user()->shop;
        $shop->verification_info = json_encode($data);
        if ($shop->save()) {
            flash(translate('Your shop verification request has been submitted successfully!'))->success();
            return redirect()->route('seller.dashboard');
        }

        flash(translate('Sorry! Something went wrong.'))->error();
        return back();
    }

    public function show()
    {
    }
}
