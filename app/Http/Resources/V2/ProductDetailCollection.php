<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Review;
use App\Models\Attribute;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Request;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Cart;
use App\Models\User;

class ProductDetailCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                $precision = 2;
                $calculable_price = home_discounted_base_price($data, false);
                $calculable_price = number_format($calculable_price, $precision, '.', '');
                $calculable_price = floatval($calculable_price);

                $edited_rep_price = calculated_rep_price($data->id);

                // $calculable_price = round($calculable_price, 2);
                $photo_paths = get_images_path($data->photos);

                $photos = [];


                if (!empty($photo_paths)) {
                    for ($i = 0; $i < count($photo_paths); $i++) {
                        if ($photo_paths[$i] != "") {
                            $item = array();
                            $item['variant'] = "";
                            $item['path'] = $photo_paths[$i];
                            $photos[] = $item;
                        }
                    }
                }

                foreach ($data->stocks as $stockItem) {
                    if ($stockItem->image != null && $stockItem->image != "") {
                        $item = array();
                        $item['variant'] = $stockItem->variant;
                        $item['path'] = uploaded_asset($stockItem->image);
                        $photos[] = $item;
                    }
                }


                // To show the offer of the product
                if($data->offers->count() > 0){
                    $user = getUserByToken();
                    $offer = $data->offers->toQuery()->active()->forAuthUser($user)->productsOffers()->first();
                    if($offer){
                        $offer_array = [];
                        if ($offer->image != null && $offer->image != "") {
                            $offer_array['banner'] = uploaded_asset($offer->banner);
                        }
                        $offer_array['title'] = $offer->title;
                        $offer_array['multiple_usage'] = $offer->multiple_usage;
                        $offer_array['is_repeatable'] = $offer->is_repeatable;
                        $offer_array['quantity_required'] = $offer->base_product->quantity;
                        $offer_array['offered_products'] = [];
                        foreach($offer->offer_products as $offer_product){
                            $offer_array['offered_products'][] = [
                                'product_id' => $offer_product->product_id,
                                'product_name' => $offer_product->product->getTranslation('name'),
                                'product_photos' => get_images_path($offer_product->product->photos),
                                'quantity' => $offer_product->quantity,
                                'main_price' => home_base_price($offer_product->product, false),
                                'discounted_price' => offer_discount($offer_product, false),
                            ];
                        }
                    }
                }

                $brand = [
                    'id' => 0,
                    'name' => "",
                    'logo' => "",
                ];

                if ($data->brand != null) {
                    $brand = [
                        'id' => $data->brand->id,
                        'name' => $data->brand->getTranslation('name'),
                        'logo' => uploaded_asset($data->brand->logo),
                    ];
                }

                $category = [
                    'id' => 0,
                    'name' => "",
                    'banner' => "",
                ];

                if ($data->category != null) {
                    $category = [
                        'id' => $data->category->id,
                        'name' => $data->category->getTranslation('name'),
                        'banner' => uploaded_asset($data->category->banner),
                    ];
                }

                $whole_sale = [];
                if (addon_is_activated('wholesale')) {
                    $whole_sale =  ProductWholesaleResource::collection($data->stocks->first()->wholesalePrices);
                }

                $productVariation = ProductVariation::where('product_id', $data->id)->get();
                $productVariation = countVariationInCart($data->id, $productVariation);
                $productStocks = $data->stocks ? $data->stocks->first()?->qty : null;

                return [
                    'id' => (int)$data->id,
                    'name' => $data->getTranslation('name'),
                    'added_by' => $data->added_by,
                    'seller_id' => $data->user->id,
                    'shop_id' => $data->added_by == 'admin' ? 0 : $data->user->shop->id,
                    'shop_name' => $data->added_by == 'admin' ? translate('In House Product') : $data->user->shop->name,
                    'shop_logo' => $data->added_by == 'admin' ? uploaded_asset(get_setting('header_logo')) : uploaded_asset($data->user->shop->logo) ?? "",
                    'photos' => $photos,
                    'thumbnail_image' => uploaded_asset($data->thumbnail_img),
                    'tags' => explode(',', $data->tags),
                    'price_high_low' => (float)explode('-', home_discounted_base_price($data, false))[0] == (float)explode('-', home_discounted_price($data, false))[1] ? format_price((float)explode('-', home_discounted_price($data, false))[0]) : "From " . format_price((float)explode('-', home_discounted_price($data, false))[0]) . " to " . format_price((float)explode('-', home_discounted_price($data, false))[1]),
                    'choice_options' => $this->convertToChoiceOptions(json_decode($data->choice_options)),
                    'colors' => json_decode($data->colors) ?? [],
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'discount' => "-" . discount_in_percentage($data) . "%",
                    'stroked_price' => home_base_price($data),
                    'main_price' => $edited_rep_price ? format_price($edited_rep_price) : home_discounted_base_price($data),
                    'price_per_piece' => get_price_per_piece($data),
                    'calculable_price' => $calculable_price,
                    'currency_symbol' => currency_symbol(),
                    'current_stock' => (int)$productStocks,
                    'unit' => $data->unit ?? "",
                    'unit_equal' => (int) $data->unit_equal,
                    'tax' => (float) $data->tax,
                    'tax_type' => $data->tax_type,
                    'rating' => (float)$data->rating,
                    'rating_count' => (int)Review::where(['product_id' => $data->id])->count(),
                    'earn_point' => (float)$data->earn_point,
                    'description' => $data->getTranslation('description'),
                    'video_link' => $data->video_link != null ?  $data->video_link : "",
                    'brand' => $brand,
                    'category' => $category,
                    'serial' => $data->serial,
                    'mat_id' => $data->mat_id,
                    'max_qty' => $data->max_qty,
                    'link' => route('product', $data->slug),
                    'wholesale' => $whole_sale,
                    'variation' => $productVariation,
                    'count_in_cart' => count_in_cart($data->id),
                    'cart_id' => cart_id_for_product($data->id),
                    'is_favorite' => is_favorite($data->id),
                    'stars' => $data->collection?->amount ?? 0,
                    'offers' => $offer_array ?? null,
                    'for_sale'  => (int) $data->for_sale,
                    'published' => (int) $data->published
                ];
            })
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }

    protected function convertToChoiceOptions($data)
    {
        $result = array();
        if ($data) {
            foreach ($data as $key => $choice) {
                $item['name'] = $choice->attribute_id;
                $item['title'] = Attribute::find($choice->attribute_id)->getTranslation('name');
                $item['options'] = $choice->values;
                array_push($result, $item);
            }
        }
        return $result;
    }

    protected function convertPhotos($data)
    {
        $result = array();
        foreach ($data as $key => $item) {
            array_push($result, uploaded_asset($item));
        }
        return $result;
    }
}
