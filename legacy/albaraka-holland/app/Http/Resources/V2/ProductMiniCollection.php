<?php

namespace App\Http\Resources\V2;

use App\Models\Cart;
use App\Models\User;
use App\Models\ProductVariation;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ProductMiniCollection extends ResourceCollection
{
    public function toArray($request)
    {


        // $cartResults = [];

        // foreach ($carts as $cart) {
        //     if (isset($cart->variation_qty) && $cart->variation_qty != 'null') {
        //         $jsonData = $cart->variation_qty;

        //         // Decode the JSON into a PHP associative array
        //         $variationData = json_decode($jsonData, true);

        //         // Initialize an empty array to store the results for this cart
        //         $results = [];

        //         // Iterate over the keys (e.g., "413" and "414") in this cart
        //         foreach ($variationData as $key => $value) {
        //             // Use the key to query the database and store the result
        //             $productVariation = ProductVariation::find($key);

        //             // Check if the record was found
        //             if ($productVariation) {
        //                 // You can add the result to your results array
        //                 $results[] = [
        //                     'key' => $key,
        //                     'value' => $value,
        //                     'productVariation' => $productVariation,
        //                 ];
        //             }
        //         }

        //         // Store the results for this cart using the cart's ID as the key
        //         $cartResults[$cart->id] = $results;
        //     }
        // }
        return [
            'data' => $this->collection->map(function ($data) {
                $wholesale_product =
                    ($data->wholesale_product == 1) ? true : false;
                $precision = 2;
                $calculable_price = home_discounted_base_price($data, false);
                $calculable_price = number_format($calculable_price, $precision, '.', '');
                $calculable_price = floatval($calculable_price);
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
                                'discounted_value' => $offer_product->discount,
                                'discounted_type' => $offer_product->discount_type,
                            ];
                        }
                    }
                }

                $productVariation = ProductVariation::where('product_id', $data->id)->get();
                $productVariation = countVariationInCart($data->id, $productVariation);

                return [
                    'id' => $data->id,
                    'name' => $data->getTranslation('name'),
                    'photos' => $photos,
                    'thumbnail_image' => uploaded_asset($data->thumbnail_img),
                    'tags' => explode(',', $data->tags),
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                  //  'discount' => "-" . discount_in_percentage($data) . "%",
                    'stroked_price' => home_base_price($data),
                    'main_price' => home_discounted_base_price($data),
                    'price_per_piece' => get_price_per_piece($data),
                    'calculable_price' => $calculable_price,
                    'colors' => json_decode($data->colors) ?? [],
                    'current_stock' => (int)$data->stocks->first()?->qty,
                    'rating' => (float) $data->rating,
                    'earn_point' => (float)$data->earn_point,
                    'description' => $data->getTranslation('description'),
                    'video_link' => $data->video_link != null ?  $data->video_link : "",
                    'sales' => (int) $data->num_of_sale,
                    'published' => (int) $data->published,
                    'for_sale' => (int) $data->for_sale,
                    'max_qty' => (int) $data->max_qty,
                    'unit' => $data->unit ?? "",
                    'unit_equal' => (int) $data->unit_equal,
                    'serial' => $data->serial,
                    'mat_id' => $data->mat_id,
                    'tax' => (float) $data->tax,
                    'tax_type' => $data->tax_type,
                    'is_wholesale' => $wholesale_product,
                    'variation' => $productVariation,
                    'offers' => $offer_array ?? null,
                    'count_in_cart' => count_in_cart($data->id),
                    'cart_id' => cart_id_for_product($data->id),
                    'is_favorite' => is_favorite($data->id),
                    'stars' => $data->collection?->amount ?? 0,
                    'links' => [
                        'details' => route('products.show', $data->id),
                    ]
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
}
