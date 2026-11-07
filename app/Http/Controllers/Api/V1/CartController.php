<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Middleware\HidePricesFromUnverified;
use App\Models\Cart;
use App\Models\CartOffered;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Shop;
use App\Models\StoreUser;
use App\Services\CartService;
use App\Services\MinimumOrderFee;
use App\Services\OfferService;
use App\Utility\NagadUtility;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class CartController extends Controller
{
    protected $offerService;

    protected function offerService()
    {
        return app(OfferService::class);
    }
    protected function cartService()
    {
        return app(CartService::class);
    }

    private function variantRefusal(): JsonResponse
    {
        $message = __('api.cart.variant_not_found');

        return response()->json(['result' => false, 'message' => $message, 'errors' => ['variant' => [$message]]], 422);
    }

    public function summary()
    {
        set_time_limit(0);

        return response()->json($this->summaryBody());
    }

    /**
     * What GET /cart-summary answers, for the signed-in account's cart.
     *
     * @return array<string, mixed>
     */
    private function summaryBody(): array
    {
        //$user = User::where('id', auth()->user()->id)->first();
        $items = auth()->user()->carts;
        if ($items->isEmpty()) {
            return [
                'sub_total' => format_price(0.00),
                'tax' => format_price(0.00),
                'shipping_cost' => format_price(0.00),
                'discount' => format_price(0.00),
                'grand_total' => format_price(0.00),
                'grand_total_value' => 0.00,
                // The same types as a filled cart's answer.
                'coupon_code' => null,
                'coupon_applied' => false,
                'delivery_cost' => format_price(0.00),
                ...$this->paymentAvailability(),
            ];
        }

        $sum = 0.00;
        $subtotal = 0.00;
        $tax = 0.00;
        foreach ($items as $cartItem) {
            $item_sum = 0.00;

            // GET /carts prices each line live, so the summary must as well. A
            // representative's own price counts only when the line says it was
            // changed: MySQL hands back an unset rep_price as the string "0.00",
            // which PHP reads as true and priced every ordinary line at nothing.
            $product = Product::find($cartItem->product_id);
            $price = $cartItem->is_price_changed
                ? $cartItem->rep_price
                : ($product ? cart_product_price($cartItem, $product, false, false) : $cartItem->price);

            // $item_sum += ($cartItem->price + $cartItem->tax) * $cartItem->quantity;
            $item_sum += $price * $cartItem->quantity;
            $item_sum += $cartItem->shipping_cost - $cartItem->discount;
            $sum +=  $item_sum;   //// 'grand_total' => $request->g

            $subtotal += $price * $cartItem->quantity;

            // VAT is added on top of the line price, as order/store adds it.
            if ($product) {
                $lineTax = cart_line_tax($cartItem, $product, (float) $price * $cartItem->quantity);
                $tax += $lineTax;
                $sum += $lineTax;
            }
        }

        // The fee line order/store adds below the minimum order (MinimumOrderFee),
        // priced and taxed as that line will be, and counted in grand_total.
        $delivery_cost = 0.00;
        $fees = app(MinimumOrderFee::class);
        $feeProduct = rescue(fn () => $fees->productFor($items), null, false);

        if ($feeProduct !== null && ! $items->contains(fn (Cart $line): bool => $fees->isFeeLine($line))) {
            $feeLine = new Cart(['variation' => '', 'quantity' => 1]);
            $delivery_cost = (float) cart_product_price($feeLine, $feeProduct, false, false);
            $feeTax = cart_line_tax($feeLine, $feeProduct, $delivery_cost);
            $tax += $feeTax;
            $sum += $delivery_cost + $feeTax;
        }

        return [
            'sub_total' => format_price($subtotal),
            'tax' => format_price($tax),
            'shipping_cost' => format_price($items->sum('shipping_cost')),
            'discount' => format_price($items->sum('discount')),
            'grand_total' => format_price($sum),
            'grand_total_value' => convert_price($sum),
            'coupon_code' => $items[0]->coupon_code,
            'coupon_applied' => $items[0]->coupon_applied == 1,
            'delivery_cost' => format_price($delivery_cost),
            ...$this->paymentAvailability(),
        ];
    }

    /**
     * A cart change's answer, with the cart the change left behind when the
     * app asked for it (`with_cart=1`): `cart` is what POST /carts answers and
     * `summary` is what GET /cart-summary answers, so the app needs no second
     * and third call. Without the parameter the answer is unchanged. Only a
     * successful change carries them, and an account that may not see prices
     * gets them blanked as those two routes would (HidePricesFromUnverified).
     */
    private function withCart(JsonResponse $response, Request $request): JsonResponse
    {
        if (! $request->boolean('with_cart') || ! $response->isSuccessful()) {
            return $response;
        }

        $body = $response->getData(true);

        if (($body['result'] ?? false) !== true) {
            return $response;
        }

        $user = $request->user();
        // This request changed the lines: read them again, not as loaded earlier in it.
        $user->unsetRelation('carts')->unsetRelation('carts_offered');

        $body['cart'] = $this->shopsFor($user);
        $body['summary'] = $this->summaryBody();
        $hidesPrices = HidePricesFromUnverified::hidesPricesFrom($user);

        if ($hidesPrices) {
            $body['cart'] = HidePricesFromUnverified::blankPrices($body['cart'], 'cart');
            $body['summary'] = [...HidePricesFromUnverified::blankPrices($body['summary'], 'cart'), 'price_hidden' => true];
        }

        // A new answer rather than $response->setData(): Scramble (the API reference)
        // cannot infer setData() on a response it did not build, and its export fails.
        $answer = response()->json($body, $response->getStatusCode());

        return $hidesPrices ? $answer->header('X-Prices-Hidden', '1') : $answer;
    }

    /**
     * What the app needs to know before the payment step, so it can disable an
     * option or warn at the cart: whether anything can be paid with, and whether
     * cash on delivery can be for this cart (with the reason when it cannot).
     * Decided by PaymentTypesController, like GET /payment-types and order/store.
     *
     * @return array{payment_available: bool, cash_on_delivery_available: bool, cash_on_delivery_message: string|null, cash_on_delivery_blocked_products: list<array{cart_id: int, product_id: int}>}
     */
    private function paymentAvailability(): array
    {
        $payments = app(PaymentTypesController::class);
        $cash = $payments->cashAvailability();

        return [
            'payment_available' => $payments->options(new Request(['mode' => 'order']))['payment_types'] !== [],
            'cash_on_delivery_available' => $cash['message'] === null,
            'cash_on_delivery_message' => $cash['message'],
            // The cart lines to remove for cash on delivery to be offered.
            'cash_on_delivery_blocked_products' => $cash['products'],
        ];
    }


    public function count()
    {
        set_time_limit(0);

        $items = auth()->user()->carts;
        $offered_items = auth()->user()->carts_offered;
        $count = sizeof($items) + sizeof($offered_items);
        return response()->json([
            'count' => $count,
            'status' => true,
        ]);
    }



    public function getList(Request $request)
    {
        set_time_limit(0);

        return response()->json($this->shopsFor($request->user()));
    }

    /**
     * What POST /carts answers: the account's cart lines, grouped by shop.
     *
     * @return list<array<string, mixed>>
     */
    private function shopsFor(StoreUser $user): array
    {
        $owner_ids = Cart::where('user_id', $user->id)->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
        $currency_symbol = currency_symbol();
        $shops = [];
        if (!empty($owner_ids)) {
            foreach ($owner_ids as $owner_id) {
                $shop = [];
                $shop_items_raw_data = Cart::where('user_id', $user->id)->where('owner_id', $owner_id)->get()->toArray();
                $shop_items_data = [];
                if (!empty($shop_items_raw_data)) {
                    foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
                        $decodedData = json_decode($shop_items_raw_data_item["variation_qty"], true);
                        // convert to array of objects
                        $transformedData = [];
                        if ($decodedData) {
                            foreach ($decodedData as $name => $quantity) {
                                $transformedData[] = [
                                    'name' => $name,
                                    'quantity' => $quantity, // Assuming you want to divide the quantity by 2, adjust as needed
                                ];
                            }
                        }

                        $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
                        $product_stocks = ProductStock::where('product_id', $product->id);
                        $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
                        $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
                        $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
                        $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
                        $shop_items_data_item["product_name"] = $product->getTranslation('name');
                        $shop_items_data_item["product_thumbnail_image"] = uploaded_asset($product->thumbnail_img);
                        $shop_items_data_item["variation"] = $shop_items_raw_data_item["variation"];
                        $shop_items_data_item["price"] = (float) cart_product_price($shop_items_raw_data_item, $product, false, false);
                        $shop_items_data_item["currency_symbol"] = $currency_symbol;
                        $shop_items_data_item["tax"] = (float) cart_product_tax($shop_items_raw_data_item, $product, false);
                        $shop_items_data_item["shipping_cost"] = (float) $shop_items_raw_data_item["shipping_cost"];
                        $shop_items_data_item["quantity"] = intval($shop_items_raw_data_item["quantity"]);
                        $shop_items_data_item["variation_qty"] = $transformedData;
                        // `tax` stays the VAT percent for the legacy app; the new app reads
                        // tax_percent and tax_amount (money, added on top of price x quantity).
                        $shop_items_data_item["tax"] = $product->tax;
                        $shop_items_data_item["tax_percent"] = product_vat_percent($product);
                        $shop_items_data_item["tax_amount"] = cart_line_tax($shop_items_raw_data_item, $product, (float) $shop_items_data_item["price"] * $shop_items_data_item["quantity"]);
                        $shop_items_data_item["mat_id"] = (string) $product->id;
                        $shop_items_data_item["item_notes"] = $shop_items_raw_data_item["item_notes"];
                        $shop_items_data_item["rep_price"] = (float) $shop_items_raw_data_item["rep_price"];
                        $shop_items_data_item["is_price_changed"] = $shop_items_raw_data_item["is_price_changed"];


                        if ($user->is_rep) {
                            $shop_items_data_item["by_rep"] = $shop_items_raw_data_item["by_rep"];
                            $shop_items_data_item["for_customer"] = $shop_items_raw_data_item["for_customer"];
                        }

                        $shop_items_data_item["lower_limit"] = intval($product->min_qty);
                        $shop_items_data_item["upper_limit"] = intval($product_stocks->where('variant', $shop_items_raw_data_item['variation'])->first()?->qty);

                        $shop_items_data[] = $shop_items_data_item;
                    }
                }

                $carts_offered = CartOffered::where('user_id', $user->id)->get();

                if ($carts_offered->count() > 0) {

                    foreach ($carts_offered as $cart_offered) {
                        $product = Product::where('id', $cart_offered->product_id)->first();

                        $cart_offered->product_name = $product->getTranslation('name');
                        $cart_offered->product_thumbnail_image = uploaded_asset($product->thumbnail_img);

                        $decodedData = json_decode($cart_offered->variation_qty, true);
                        // convert to array of objects
                        $transformedData = [];
                        if ($decodedData) {
                            foreach ($decodedData as $name => $quantity) {
                                $transformedData[] = [
                                    'name' => $name,
                                    'quantity' => $quantity, // Assuming you want to divide the quantity by 2, adjust as needed
                                ];
                            }
                        }
                        $cart_offered->variation_qty = $transformedData ?? null;
                        $cart_offered->mat_id = (string) $product->id;
                        $cart_offered->rep_price = null;
                        $cart_offered->is_price_changed = null;
                        $cart_offered->offer_cart = true;
                        $cart_offered->tax = 9;
                        $cart_offered->shipping_cost = 0;
                        $cart_offered->currency_symbol = "€";
                        $shop_items_data[] = $cart_offered;
                    }
                }

                $shop_data = Shop::where('user_id', $owner_id)->first();
                if ($shop_data) {
                    $shop['name'] = $shop_data->name;
                    $shop['owner_id'] = (int) $owner_id;
                    $shop['cart_items'] = $shop_items_data;
                } else {
                    $shop['name'] = "Inhouse";
                    $shop['owner_id'] = (int) $owner_id;
                    $shop['cart_items'] = $shop_items_data;
                }
                $shops[] = $shop;
            }
        }

        //dd($shops);

        return $shops;
    }


    public function add(Request $request)
    {
        set_time_limit(0);

        if (Auth::check() && Auth::user()->is_rep) {
            if (!auth()->user()->has_open_visit()) {
                return response()->json([
                    'result' => false,
                    'message' => __('storefront.cart.visit_required')
                ], 403);
            }
        }


        $product = Product::with('stocks')->find($request->id);
        if (!$product) {
            return response()->json(['result' => false, 'message' => __('api.product.not_found')], 404);
        }
        $authUser = $request->user();
        $product_stocks = ProductStock::where('product_id', $product->id);
        $is_rep = $authUser->is_rep ?? false;
        $carts = Cart::where("user_id", auth()->id())->get();
        foreach ($carts as $cart) {
            if (isset($cart->for_customer)) {
                $for_customer = $cart->for_customer;
            }
        }
        $variant = $request->variant;
        $tax = 0;

        if ($variant !== null && ! is_string($variant)) {
            return $this->variantRefusal();
        }

        if ($variant == '')
            $price = $product->unit_price;
        else {
            $product_stock = $product_stocks->where('variant', 'like', '%' . $variant . '%')->first();

            // A variant the product does not have used to end in a 500.
            if ($product_stock === null) {
                return $this->variantRefusal();
            }

            $price = $product_stock->price;
        }


        if (isset($request->variation_qty)) {

            $variations = $request->variation_qty;

            // Initialize an array to store valid variations
            $validVariations = [];
            // Iterate through variations and filter out those with value 0
            foreach ($variations as $name => $value) {
                if ($value > 0) {
                    // Add the valid variation to the array
                    $validVariations[$name] = $value;
                }
            }
            $variation_qty = json_encode($validVariations);
        }


        // A note that is sent replaces the old one, and a blank one (empty,
        // null or only spaces) clears it. A request without the key leaves the
        // note as it is: `isset()` could not tell "clear it" from "not sent".
        $note_sent = $request->exists('item_notes');
        $item_notes = $note_sent ? $this->cleanNote($request->input('item_notes')) : null;



        if (isset($request->rep_price)) {
            $discounted_price = $request->rep_price; // the price which the rep entered
            $original_price = $price;
            $representative_discount_percentage  = Auth::user()->rep_discount_percentage;
            if (! $representative_discount_percentage) { // if the rep does not have a package
                return response()->json(['result' => false, 'message' => __('api.cart.representative_price_only')], 403);
            }
            $representative_discount_percentage  = $representative_discount_percentage  / 100;
            $minimum_discount_price = $original_price - ($original_price * $representative_discount_percentage); // the minimum limit
            if ($discounted_price >= $minimum_discount_price) {
                if ($discounted_price <= $original_price) {
                    $rep_price = $discounted_price;
                    $is_price_changed = true;
                } else {
                    return response()->json(['result' => false, 'message' => __('api.cart.price_above_original')], 403);
                }
            } else {
                return response()->json(['result' => false, 'message' => __('api.cart.discount_limit_exceeded')], 403);
            }
        }
        //discount calculation based on flash deal and regular discount
        //calculation of taxes
        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        } elseif (
            strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
        ) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if ($product->discount_type == 'percent') {
                $price -= ($price * $product->discount) / 100;
            } elseif ($product->discount_type == 'amount') {
                $price -= $product->discount;
            }
        }

        foreach ($product->taxes as $product_tax) {
            if ($product_tax->tax_type == 'percent') {
                $tax += ($price * $product_tax->tax) / 100;
            } elseif ($product_tax->tax_type == 'amount') {
                $tax += $product_tax->tax;
            }
        }

        if (filter_var($request->quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return response()->json(['result' => false, 'message' => __('storefront.common.something_went_wrong')], 200);
        }

        if ($product->min_qty > $request->quantity) {
            return response()->json(['result' => false, 'message' => trans_choice('api.cart.minimum_quantity', $product->min_qty, ['count' => $product->min_qty])], 200);
        }

        if ($product->max_qty < $request->quantity) {
            return response()->json(['result' => false, 'message' => trans_choice('api.cart.maximum_quantity', $product->max_qty, ['count' => $product->max_qty])], 200);
        }



        $stock = $product_stocks->where('variant', 'like', '%' . $variant . '%')->first()->qty;

        $variant_string = $variant != null && $variant != "" ? " ($variant)" : "";
        if ($stock < $request->quantity && $product->digital == 0) {
            if ($stock == 0) {
                return response()->json(['result' => false, 'message' => __('api.cart.stock_out')], 200);
            } else {
                return response()->json(['result' => false, 'message' => trans_choice('api.cart.available_quantity', $stock, ['count' => $stock, 'variant' => $variant_string])], 200);
            }
        }

        $cart_item = Cart::where('product_id', $request->id)->where("user_id", auth()->id())->where('variation', $variant)->first();
        if ($cart_item && $cart_item->product->digital == 1) {
            return response()->json(['result' => false, 'message' => __('api.cart.already_added')]);
        }

        if ($cart_item) {
            $variationQty = json_decode($cart_item->variation_qty, true); //old variation
            if (isset($variation_qty)) {
                $newVariation =  json_decode($variation_qty, true); // new variation
                // Merge old and new variations
                if (is_array($newVariation)) {
                    if (is_null($variationQty)) {
                        $variationQty = [];
                    }
                    $variationQty = array_merge($variationQty, $newVariation);
                }
            }

            if ($note_sent) { // update
                $cart_item->item_notes = $item_notes;
            }


            if (isset($request->rep_price)) {
                $discounted_price = $request->rep_price; // the price which the rep entered
                $original_price = $price;
                $representative_discount_percentage  = Auth::user()->rep_discount_percentage;
                if (! $representative_discount_percentage) { // if the rep does not have a package
                    return response()->json(['result' => false, 'message' => __('api.cart.representative_price_only')], 403);
                }
                $representative_discount_percentage  = $representative_discount_percentage  / 100;
                $minimum_discount_price = $original_price - ($original_price * $representative_discount_percentage); // the minimum limit
                if ($discounted_price >= $minimum_discount_price) {
                    if ($discounted_price <= $original_price) {
                        $rep_price = $discounted_price;
                        $is_price_changed = true;
                    } else {
                        return response()->json(['result' => false, 'message' => __('api.cart.price_above_original')], 403);
                    }
                } else {
                    return response()->json(['result' => false, 'message' => __('api.cart.discount_limit_exceeded')], 403);
                }
            } //

            $cart_item->save();
            $product_quantity_in_cart = $cart_item->quantity;
        }




        $cart_data = Cart::updateOrCreate([
            'user_id' => $authUser->id,
            'owner_id' => $product->user_id,
            'product_id' => $request->id,
            'variation' => $variant,
        ], [
            'price' => $price,
            'rep_price' => $rep_price ?? 0,
            'is_price_changed' => $is_price_changed ?? false,
            'item_notes' => $note_sent ? $item_notes : ($cart_item->item_notes ?? null),
            'tax' => $tax, // User Experience
            'shipping_cost' => 0,
            'quantity' => $request->quantity,
            'for_customer' => isset($for_customer) ? $for_customer : null,
            'by_rep' => $is_rep,
            'variation_qty' => isset($variationQty) ? json_encode($variationQty) : (isset($variation_qty) ? $variation_qty : null)
        ]);

        $offerMessage = $this->offerService()->processOffer($product, $cart_data, $product_quantity_in_cart ?? $request->quantity, $request);

        if (Auth::check() && Auth::user()->is_rep) {
            $this->cartService()->setVisitCustomerToCart();
        } elseif (Auth::user()->is_rep) {
            $this->cartService()->setVisitCustomerToCart();
        }

        if (NagadUtility::create_balance_reference($request->cost_matrix) == false) {
            return response()->json(['result' => false, 'message' => __('api.cart.cost_error')]);
        }

        return $this->withCart(response()->json([
            'result' => true,
            'message' => __('api.cart.added'),
            'offer_message' => $offerMessage ?? null
        ]), $request);
    }

    /**
     * Change or clear the note on a cart line (an empty note clears it).
     * Takes the cart line's own id, like change-quantity and DELETE /carts/{id}.
     */
    public function updateNote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'item_notes' => 'present|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => __('api.common.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $cart = Cart::where('id', $request->id)->where('user_id', auth()->user()->id)->first();

        if ($cart === null) {
            return response()->json(['result' => false, 'message' => __('api.cart.item_not_found')], 404);
        }

        $cart->update(['item_notes' => $this->cleanNote($request->input('item_notes'))]);

        return response()->json(['result' => true, 'message' => __('api.cart.updated')]);
    }

    /** A note is trimmed, and one with nothing in it is no note (null). */
    private function cleanNote(mixed $note): ?string
    {
        $note = is_string($note) ? trim($note) : '';

        return $note === '' ? null : $note;
    }

    public function changeQuantity(Request $request)
    {
        // Only the signed-in account's own cart lines, and a whole number of at
        // least one: a negative quantity would make the order total negative.
        $cart = filter_var($request->quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            ? null
            : Cart::where('id', $request->id)->where('user_id', auth()->user()->id)->first();
        if ($cart != null) {

            if ($cart->product->stocks->where('variant', $cart->variation)->first()->qty >= $request->quantity) {
                $cart->update([
                    'quantity' => $request->quantity
                ]);

                return $this->withCart(response()->json(['result' => true, 'message' => __('api.cart.updated')], 200), $request);
            } else {
                return response()->json(['result' => false, 'message' => __('api.cart.maximum_available')], 200);
            }
        }

        return response()->json(['result' => false, 'message' => __('storefront.common.something_went_wrong')], 200);
    }

    public function process(Request $request)
    {
        $cart_ids = explode(",", $request->cart_ids);
        $cart_quantities = explode(",", $request->cart_quantities);

        if (!empty($cart_ids)) {
            $i = 0;
            foreach ($cart_ids as $cart_id) {
                $cart_item = Cart::where('id', $cart_id)->where('user_id', auth()->user()->id)->first();
                if ($cart_item) {
                    if (filter_var($cart_quantities[$i] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                        return response()->json(['result' => false, 'message' => __('storefront.common.something_went_wrong')], 200);
                    }

                    $product = Product::where('id', $cart_item->product_id)->first();

                    if ($product->min_qty > $cart_quantities[$i]) {
                        return response()->json(['result' => false, 'message' => trans_choice('api.cart.minimum_product_quantity', $product->min_qty, ['count' => $product->min_qty, 'product' => $product->name])], 200);
                    }

                    if ($product->max_qty < $cart_quantities[$i]) {
                        return response()->json(['result' => false, 'message' => trans_choice('api.cart.maximum_product_quantity', $product->max_qty, ['count' => $product->max_qty, 'product' => $product->name])], 200);
                    }

                    $stock = $cart_item->product->stocks->where('variant', $cart_item->variation)->first()->qty;
                    $variant_string = $cart_item->variation != null && $cart_item->variation != "" ? " ($cart_item->variation)" : "";
                    if ($stock >= $cart_quantities[$i] || $product->digital == 1) {
                        $cart_item->update([
                            'quantity' => $cart_quantities[$i]
                        ]);
                    } else {
                        if ($stock == 0) {
                            return response()->json(['result' => false, 'message' => __('api.cart.product_unavailable', ['product' => $product->name, 'variant' => $variant_string])], 200);
                        } else {
                            return response()->json(['result' => false, 'message' => trans_choice('api.cart.product_available_quantity', $stock, ['count' => $stock, 'product' => $product->name, 'variant' => $variant_string])], 200);
                        }
                    }
                }

                $i++;
            }

            return response()->json(['result' => true, 'message' => __('api.cart.updated')], 200);
        } else {
            return response()->json(['result' => false, 'message' => __('api.cart.empty')], 200);
        }
    }

    public function destroy(Request $request, $id)
    {
        // The customer's own cart item only: this deleted any cart row by id.
        $cart = Cart::where('id', $id)->where('user_id', auth()->user()->id)->first();

        if (! $cart) {
            return response()->json(['result' => false, 'message' => __('api.cart.item_not_found')], 404);
        }

        $this->offerService()->removeProductOfferFromCart($cart->id);

        $cart->delete();

        return $this->withCart(response()->json(['result' => true, 'message' => __('api.cart.removed')], 200), $request);
    }
    public function update_item_price(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:carts,id',
            'rep_price' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $cart = Cart::where('user_id', Auth::user()->id)->where('id', $request->id)->first();

        $discounted_price = $request->rep_price; // the price which the rep entered
        $original_price = $cart->price;
        $representative_discount_percentage  = Auth::user()->rep_discount_percentage;
        if (! $representative_discount_percentage) { // if the rep does not have a package
            return response()->json(['result' => false, 'message' => __('api.cart.representative_price_only')], 403);
        }
        $representative_discount_percentage  = $representative_discount_percentage  / 100;
        $minimum_discount_price = $original_price - ($original_price * $representative_discount_percentage); // the minimum limit
        if ($discounted_price >= $minimum_discount_price) {
            if ($discounted_price <= $original_price) {
                $cart->rep_price = $discounted_price;
                $cart->is_price_changed = true;
                if ($cart->save()) {
                    return $this->withCart(response()->json(['result' => true, 'message' => __('api.cart.price_updated')], 200), $request);
                } else {
                    return response()->json(['result' => false, 'message' => __('api.common.error')], 200);
                }
            } else {
                return response()->json(['result' => false, 'message' => __('api.cart.price_above_original')], 403);
            }
        } else {
            return response()->json(['result' => false, 'message' => __('api.cart.discount_limit_exceeded')], 403);
        }
    }
}
