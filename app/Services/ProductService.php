<?php

namespace App\Services;

use App\Models\Color;
use App\Models\Product;
use App\Models\StoreUser;
use App\Utility\ProductUtility;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * The only request fields a seller's product form may set. Everything the
     * service decides itself (`user_id`, `approved`, `published` on update,
     * `featured`, `todays_deal`, `added_by`, ...) is left out, so a forged
     * field never reaches the model, whatever the model's own mass-assignment
     * rules turn out to allow.
     *
     * @var list<string>
     */
    private const SELLER_FIELDS = [
        'name', 'slug', 'category_id', 'brand_id', 'unit', 'weight', 'min_qty', 'tags', 'barcode', 'refundable',
        'photos', 'thumbnail_img', 'video_provider', 'video_link', 'external_link', 'external_link_btn',
        'unit_price', 'discount', 'discount_type', 'date_range', 'current_stock', 'description', 'shipping_type',
        'flat_shipping_cost', 'est_shipping_days', 'low_stock_quantity', 'stock_visibility_state',
        'cash_on_delivery', 'is_quantity_multiplied', 'meta_title', 'meta_description', 'meta_img', 'pdf',
        'colors_active', 'colors', 'choice_no', 'button', 'lang',
    ];

    /**
     * `$input` cut down to what a seller may set: the fields above plus the
     * per-attribute and per-variant keys the form generates (`choice_options_3`,
     * `price_Red-M`, `sku_...`, `qty_...`, `img_...`).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sellerAttributes(array $input): array
    {
        return array_filter(
            $input,
            fn (string $key): bool => in_array($key, self::SELLER_FIELDS, true)
                || preg_match('/^(choice_options_\d+|(price|sku|qty|img)_.+)$/', $key) === 1,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function store(array $data)
    {
        $collection = collect($data);

        $approved = 1;
        if (auth()->user()->user_type == 'seller') {
            $user_id = auth()->user()->id;
            if (get_setting('product_approve_by_admin') == 1) {
                $approved = 0;
            }
        } else {
            $user_id = StoreUser::where('user_type', 'admin')->where('raizer_admin', null)->first()->id;
        }
        $tags = [];
        if ($collection['tags'][0] != null) {
            foreach (json_decode($collection['tags'][0]) as $key => $tag) {
                array_push($tags, $tag->value);
            }
        }
        $collection['tags'] = implode(',', $tags);
        $discount_start_date = null;
        $discount_end_date   = null;
        if ($collection['date_range'] != null) {
            $date_var               = explode(" to ", $collection['date_range']);
            $discount_start_date = strtotime($date_var[0]);
            $discount_end_date   = strtotime($date_var[1]);
        }
        unset($collection['date_range']);

        if ($collection['meta_title'] == null) {
            $collection['meta_title'] = $collection['name'];
        }
        if ($collection['meta_description'] == null) {
            $collection['meta_description'] = strip_tags($collection['description']);
        }

        if ($collection['meta_img'] == null) {
            $collection['meta_img'] = $collection['thumbnail_img'];
        }


        $shipping_cost = 0;
        if (isset($collection['shipping_type'])) {
            if ($collection['shipping_type'] == 'free') {
                $shipping_cost = 0;
            } elseif ($collection['shipping_type'] == 'flat_rate') {
                $shipping_cost = $collection['flat_shipping_cost'];
            }
        }
        unset($collection['flat_shipping_cost']);

        $slug = Str::slug($collection['name']);
        $same_slug_count = Product::where('slug', 'LIKE', $slug . '%')->count();
        $slug_suffix = $same_slug_count ? '-' . $same_slug_count + 1 : '';
        $slug .= $slug_suffix;

        $colors = json_encode([]);
        if (
            isset($collection['colors_active']) &&
            $collection['colors_active'] &&
            $collection['colors'] &&
            count($collection['colors']) > 0
        ) {
            $colors = json_encode($collection['colors']);
        }

        $options = ProductUtility::get_attribute_options($collection);

        $combinations = ProductUtility::makeCombinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = ProductUtility::get_combination_string($combination, $collection);

                unset($collection['price_' . str_replace('.', '_', $str)]);
                unset($collection['sku_' . str_replace('.', '_', $str)]);
                unset($collection['qty_' . str_replace('.', '_', $str)]);
                unset($collection['img_' . str_replace('.', '_', $str)]);
            }
        }

        unset($collection['colors_active']);

        $choice_options = [];
        if (isset($collection['choice_no']) && $collection['choice_no']) {
            $str = '';
            $item = [];
            foreach ($collection['choice_no'] as $key => $no) {
                $str = 'choice_options_' . $no;
                $item['attribute_id'] = $no;
                $attribute_data = [];
                // foreach (json_decode($request[$str][0]) as $key => $eachValue) {
                foreach ($collection[$str] as $key => $eachValue) {
                    // array_push($data, $eachValue->value);
                    array_push($attribute_data, $eachValue);
                }
                unset($collection[$str]);

                $item['values'] = $attribute_data;
                array_push($choice_options, $item);
            }
        }

        $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);

        if (isset($collection['choice_no']) && $collection['choice_no']) {
            $attributes = json_encode($collection['choice_no']);
            unset($collection['choice_no']);
        } else {
            $attributes = json_encode([]);
        }

        $published = 1;
        if ($collection['button'] == 'unpublish' || $collection['button'] == 'draft') {
            $published = 0;
        }
        unset($collection['button']);

        $data = $collection->merge(compact(
            'user_id',
            'approved',
            'discount_start_date',
            'discount_end_date',
            'shipping_cost',
            'slug',
            'colors',
            'choice_options',
            'attributes',
            'published',
        ))->toArray();

        return Product::create($data);
    }

    public function update(array $data, Product $product)
    {
        $collection = collect($data);

        $slug = Str::slug($collection['name']);
        $slug = $collection['slug'] ? Str::slug($collection['slug']) : Str::slug($collection['name']);
        $same_slug_count = Product::where('slug', 'LIKE', $slug . '%')->count();
        $slug_suffix = $same_slug_count > 1 ? '-' . $same_slug_count + 1 : '';
        $slug .= $slug_suffix;

        if(addon_is_activated('refund_request') && !isset($collection['refundable'])){
            $collection['refundable'] = 0;
        }

        if(!isset($collection['is_quantity_multiplied'])){
            $collection['is_quantity_multiplied'] = 0;
        }

        if(!isset($collection['cash_on_delivery'])){
            $collection['cash_on_delivery'] = 0;
        }
        if(!isset($collection['featured'])){
            $collection['featured'] = 0;
        }
        if(!isset($collection['todays_deal'])){
            $collection['todays_deal'] = 0;
        }


        $tags = [];
        if ($collection['tags'][0] != null) {
            foreach (json_decode($collection['tags'][0]) as $key => $tag) {
                array_push($tags, $tag->value);
            }
        }
        $collection['tags'] = implode(',', $tags);
        $discount_start_date = null;
        $discount_end_date   = null;
        if ($collection['date_range'] != null) {
            $date_var               = explode(" to ", $collection['date_range']);
            $discount_start_date = strtotime($date_var[0]);
            $discount_end_date   = strtotime($date_var[1]);
        }
        unset($collection['date_range']);

        if ($collection['meta_title'] == null) {
            $collection['meta_title'] = $collection['name'];
        }
        if ($collection['meta_description'] == null) {
            $collection['meta_description'] = strip_tags($collection['description']);
        }

        if ($collection['meta_img'] == null) {
            $collection['meta_img'] = $collection['thumbnail_img'];
        }

        if ($collection['lang'] != config("app.default_language")) {
            unset($collection['name']);
            unset($collection['unit']);
            unset($collection['description']);
        }
        unset($collection['lang']);


        $shipping_cost = 0;
        if (isset($collection['shipping_type'])) {
            if ($collection['shipping_type'] == 'free') {
                $shipping_cost = 0;
            } elseif ($collection['shipping_type'] == 'flat_rate') {
                $shipping_cost = $collection['flat_shipping_cost'];
            }
        }
        unset($collection['flat_shipping_cost']);

        $colors = json_encode([]);
        if (
            isset($collection['colors_active']) &&
            $collection['colors_active'] &&
            $collection['colors'] &&
            count($collection['colors']) > 0
        ) {
            $colors = json_encode($collection['colors']);
        }

        $options = ProductUtility::get_attribute_options($collection);

        $combinations = ProductUtility::makeCombinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = ProductUtility::get_combination_string($combination, $collection);

                unset($collection['price_' . str_replace('.', '_', $str)]);
                unset($collection['sku_' . str_replace('.', '_', $str)]);
                unset($collection['qty_' . str_replace('.', '_', $str)]);
                unset($collection['img_' . str_replace('.', '_', $str)]);
            }
        }

        unset($collection['colors_active']);

        $choice_options = [];
        if (isset($collection['choice_no']) && $collection['choice_no']) {
            $str = '';
            $item = [];
            foreach ($collection['choice_no'] as $key => $no) {
                $str = 'choice_options_' . $no;
                $item['attribute_id'] = $no;
                $attribute_data = [];
                // foreach (json_decode($request[$str][0]) as $key => $eachValue) {
                foreach ($collection[$str] as $key => $eachValue) {
                    // array_push($data, $eachValue->value);
                    array_push($attribute_data, $eachValue);
                }
                unset($collection[$str]);

                $item['values'] = $attribute_data;
                array_push($choice_options, $item);
            }
        }

        $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);

        if (isset($collection['choice_no']) && $collection['choice_no']) {
            $attributes = json_encode($collection['choice_no']);
            unset($collection['choice_no']);
        } else {
            $attributes = json_encode([]);
        }

        unset($collection['button']);

        $data = $collection->merge(compact(
            'discount_start_date',
            'discount_end_date',
            'shipping_cost',
            'slug',
            'colors',
            'choice_options',
            'attributes',
        ))->toArray();

        $product->update($data);

        return $product;
    }

    public function setBrandWiseDiscount(array $data)
    {
        $auth_user      = auth()->user();
        $discount_start_date = null;
        $discount_end_date   = null;
        if ($data['date_range'] != null) {
            $date_var               = explode(" to ", $data['date_range']);
            $discount_start_date = strtotime($date_var[0]);
            $discount_end_date   = strtotime($date_var[1]);
        }
        $seller_product_discount =  isset($data['seller_product_discount']) ? $data['seller_product_discount'] : null ;
        $admin_id = StoreUser::where('user_type', 'admin')->first()->id;

        $products = Product::where('brand_id', $data['brand_id'])->where('auction_product', 0);
        if(in_array($auth_user->user_type, ['admin', 'staff']) && $seller_product_discount == 0){
            $products = $products->where('user_id', $admin_id);
        }
        elseif($auth_user->user_type == 'seller'){
            $products = $products->where('user_id', $auth_user->id);
        }

        $products->update([
            'discount' => $data['discount'],
            'discount_type' => 'percent',
            'discount_start_date' => $discount_start_date,
            'discount_end_date' => $discount_end_date,
        ]);
        return 1;
    }
}
