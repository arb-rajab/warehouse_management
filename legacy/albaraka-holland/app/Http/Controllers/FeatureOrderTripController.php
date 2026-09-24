<?php

namespace App\Http\Controllers;

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Utility\NotificationUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeatureOrderTripController extends Controller
{
    public function create()
    {
        return view('backend.sales.new-order');
    }


    public function getNeededData(Request $request)
    {
        $user_id = $request->user_id;
        $product_ids = $request->product_ids;
        // $rep_id = $request->rep_id;

        return view('backend.sales.user-data-table', compact('user_id', 'product_ids'));
    }

    public function store(Request $request)
    {
        // return $request->all();
        $customer = User::findOrFail($request->customer_id);
        // $rep = User::findOrFail($request->rep_id);

        $shippingAddress = $this->getShippingAddress($customer);
        $combinedOrder = $this->createCombinedOrder($customer, $shippingAddress);
        $order = $this->createOrder($request, $combinedOrder, $customer);

        $this->processOrderProducts($request, $order, $combinedOrder);
        $this->calculateOrderProfits($order);

        $this->updateUserStats($customer, $order->profits);

        flash(translate('Your order has been placed successfully'))->success();
        return redirect()->route('trips.orders');
    }

    private function getShippingAddress(User $customer)
    {
        $address = $customer->addresses()->whereNotNull('address')->first();
        if (!$address) return [];

        return [
            'name' => $customer->name,
            'email' => $customer->email,
            'address' => $address->address,
            'country' => $address->country?->name,
            'state' => $address->state?->name,
            'city' => $address->city?->name,
            'postal_code' => $address->postal_code,
            'phone' => $address->phone,
            'lat_lang' => $address->latitude && $address->longitude ? "$address->latitude,$address->longitude" : null,
        ];
    }

    private function createCombinedOrder(User $customer, array $shippingAddress)
    {
        return CombinedOrder::create([
            'user_id' => $customer->id,
            'shipping_address' => json_encode($shippingAddress),
        ]);
    }

    private function createOrder(Request $request, CombinedOrder $combinedOrder, User $customer)
    {
        return Order::create([
            'combined_order_id' => $combinedOrder->id,
            'user_id' => $customer->id,
            'seller_id' => 9,
            'additional_info' => $request->additional_info,
            'delivery_date' => $request->delivery_date,
            'delivery_status' => 'ready_for_delivery',
            'shipping_address' => $combinedOrder->shipping_address,
            'shipping_type' => 'home_delivery',
            'order_from' => 'web',
            'company_address' => $customer->company_address,
            'company_shipping_address' => $customer->shipping_address,
            'company_tax_number' => $customer->tax_number,
            'member_serial' => $customer->member_serial,
            'payment_type' => 'cash_on_delivery',
            'delivery_viewed' => '0',
            'payment_status_viewed' => '0',
            'code' => now()->format('Ymd-His') . rand(10, 99),
            'date' => now()->timestamp,
            'payment_status' => 'unpaid',
            'by_rep' => false,
            // 'for_customer' => $customer->id,
        ]);
    }

    private function processOrderProducts(Request $request, Order $order, CombinedOrder $combinedOrder)
    {
        foreach ($request->products as $id) {
            $product = Product::findOrFail($id);
            if (!$product->published) continue;
            Log::info($product);
            Log::info($request["variations_$id"]);
            $price = $request["edited_price_$id"] ?? $this->getProductPrice($product, $request["variations_$id"] ?? '');

            $quantity = $request["quantity_$id"];

            if ($quantity === null) {
                $variationQty = is_array($request["variation_qty_$id"]) ? collect($request["variation_qty_$id"]) : collect(json_decode($request["variation_qty_$id"], true))
                    ->filter(function ($qty) {
                        return $qty > 0;
                    });

                $quantity = $variationQty->sum();
            }

            $this->validateStockAvailability($product, $quantity, $order, $combinedOrder);
            Log::info($price);
            Log::info($quantity);
            $orderDetail = new OrderDetail([
                'order_id' => $order->id,
                'seller_id' => $product->user_id,
                'product_matId' => $product->mat_id,
                'product_name' => $product->ar_name,
                'product_id' => $product->id,
                'product_serial' => $product->serial,
                'variation' => $request["variations_$id"],
                'variation_qty' => json_encode($request["variation_qty_$id"]) == 'null' ? null : json_encode($request["variation_qty_$id"]),
                'price' => $price * $quantity,
                'original_price' => $product->unit_price,
                'is_price_changed' => $request->input("edited_price_$id") ? true : false,
                'quantity' => $quantity,
                'tax' => $product->tax ?? 0,
                'shipping_type' => 'home_delivery',
                'shipping_cost' => 0.00,
                'item_notes' => $request["item_notes_$id"] ?? null,
                'profits' => (float)($price * $product->collection?->amount ?? 0) / 100,
            ]);

            $orderDetail->save();
            $product->increment('num_of_sale', (int)$quantity);

            $order->grand_total += $orderDetail->price;
        }

        $order->save();
        $combinedOrder->update(['grand_total' => $order->grand_total]);
    }

    private function calculateOrderProfits(Order $order,)
    {
        $order->profits = $order->get_order_details_profits();
        $order->discount_percent = 100 - (($order->get_order_details_price() / ($order->get_order_details_original_price() == 0 ? 1 : $order->get_order_details_original_price()) * 100));
        $order->indicator = ($order->get_order_details_profits() / $order->get_order_details_price()) * 100;
        $order->save();
    }

    private function getProductPrice(Product $product, $variant)
    {
        return ProductStock::where('product_id', $product->id)
            ->where('variant', 'like', "%$variant%")
            ->value('price') ?? 0;
    }

    private function validateStockAvailability(Product $product, $quantity, Order $order, CombinedOrder $combinedOrder)
    {
        if ($product->digital) return;

        $productStock = ProductStock::where('product_id', $product->id)->first();
        if ($quantity > $productStock->qty) {
            $order->delete();
            $combinedOrder->delete();
            abort(400, translate('The requested quantity is not available for ') . $product->name);
        }

        $productStock->decrement('qty', (int)$quantity);
    }

    private function updateUserStats(User $customer, $orderProfits)
    {
        $customer->update([
            'last_order_at' => now()->toDateString(),
            'profits' => $customer->profits + $orderProfits,
        ]);
    }


    public function getRepDiscount(Request $request)
    {
        $rep = User::find($request->rep_id);

        if ($rep && $rep->rep_discount_percentage) {
            return response()->json([
                'result' => true,
                'discount_percentage' => $rep->rep_discount_percentage,
            ]);
        }

        return response()->json([
            'result' => false,
            'message' => 'Representative does not have a discount package.'
        ]);
    }

    public function newCreate()
    {
        return view('backend.sales.new-shipping-order');
    }

    public function newEdit($id)
    {
         $order = Order::findOrFail($id);
    $customers = User::where('user_type', 'customer')->orderBy('name', 'desc')->get();
    $products = Product::where('published', 1)->where('approved', 1)->orderBy('created_at', 'desc')->get();

    return view('backend.sales.edit-shipping-order', compact('order', 'customers', 'products'));
    }

    public function newStore(Request $request)
    {
        // return $request->all();
        $customer = User::findOrFail($request->customer_id);
        // $rep = User::findOrFail($request->rep_id);

        $shippingAddress = $this->getShippingAddress($customer);
        $combinedOrder = $this->createCombinedOrder($customer, $shippingAddress);
        $order = $this->createOrder($request, $combinedOrder, $customer);

        $this->newProcessOrderProducts($request, $order, $combinedOrder);
        $this->calculateOrderProfits($order);

        $this->updateUserStats($customer, $order->profits);

        flash(translate('Your order has been placed successfully'))->success();

        return route('all_orders.show', ['id' => encrypt($order->id)]);
        // return redirect()->route('trips.orders');
    }

   public function newUpdate(Request $request, $id)
    {
        $order = Order::findOrFail($id); 

        $customer = User::findOrFail($request->customer_id);
        
        $order->update([
            $order->user_id       = $customer->id,
            $order->delivery_date = $request->delivery_date,
            $order->manager_notes = $request->additional_info,
        ]);

        $order->orderDetails()->delete();

        foreach ($request->products as $product) {
            $order->orderDetails()->create([
                'product_id' => $product['product_id'],
                'product_name' => Product::findOrFail($product['product_id'])->name,
                'quantity' => $product['quantity'],
                'price' => $product['price'],
                'tax' => $product['tax'],
            ]);
        }

        $this->calculateOrderProfits($order);

        $trip = $order->trip; 

        if ($trip && $trip->driver && $trip->driver->device_token) {
            $driver = $trip->driver;
        
       NotificationUtility::sendPushNotificationToUser(
            $driver,
            'تم تعديل الطلبية',
            'الطلبية رقم ' . $order->code . ' تم تعديلها، الرجاء مراجعتها في التطبيق.'
        );
    }

        flash(translate('Order updated successfully'))->success();
        return route('all_orders.show', ['id' => encrypt($order->id)]);
    }


    public function newGetNeededData(Request $request)
    {
        $user_id = $request->user_id;
        $product_ids = $request->product_ids;
        // $rep_id = $request->rep_id;

        return view('backend.sales.user-data-table', compact('user_id', 'product_ids'));
    }
    private function newProcessOrderProducts(Request $request, Order $order, CombinedOrder $combinedOrder)
    {
        foreach ($request->products as $TempProduct) {
            $product = Product::findOrFail($TempProduct['product_id']);
            if (!$product->published) continue;

            $price = $TempProduct['price'];
            $quantity = $TempProduct['quantity'];

            $this->validateStockAvailability($product, $quantity, $order, $combinedOrder);

            $orderDetail = new OrderDetail([
                'order_id' => $order->id,
                'seller_id' => $product->user_id,
                'product_matId' => $product->mat_id,
                'product_name' => $product->ar_name,
                'product_id' => $product->id,
                'product_serial' => $product->serial,
                'variation' => $TempProduct['unit'],
                'variation_qty' => '[]',
                'price' => $price * $quantity,
                'original_price' => $product->unit_price,
                'is_price_changed' => false,
                'quantity' => $quantity,
                'tax' => $product->tax ?? 0,
                'shipping_type' => 'home_delivery',
                'shipping_cost' => 0.00,
                'item_notes' => $TempProduct['info'] ?? null,
                'profits' => (float)($price * $product->collection?->amount ?? 0) / 100,
            ]);

            $orderDetail->save();
            $product->increment('num_of_sale', (int)$quantity);

            $order->grand_total += $orderDetail->price;
        }

        $order->save();
        $combinedOrder->update(['grand_total' => $order->grand_total]);
    }
}
