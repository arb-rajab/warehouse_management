<?php

namespace App\Http\Controllers;

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Http\Request;

class FeatureOrderController extends Controller
{
    public function create()
    {
        return view('backend.sales.new-new-order');
    }


    public function getNeededData(Request $request)
    {
        $user_id = $request->user_id;
        $product_ids = $request->product_ids;
        $rep_id = $request->rep_id;

        return view('backend.sales.user-data-table', compact('user_id', 'rep_id', 'product_ids'));
    }

    public function store(Request $request)
    {
        $customer = User::findOrFail($request->customer_id);
        $rep = User::findOrFail($request->rep_id);

        $shippingAddress = $this->getShippingAddress($customer);
        $combinedOrder = $this->createCombinedOrder($rep, $shippingAddress);
        $order = $this->createOrder($request, $combinedOrder, $customer, $rep);

        $this->newProcessOrderProducts($request, $order, $combinedOrder);
        $this->calculateOrderProfits($order);

        $this->updateUserStats($rep, $customer, $order->profits);

        flash(translate('Your order has been placed successfully'))->success();

        return route('all_orders.show', ['id' => encrypt($order->id)]);
        // return redirect()->route('all_orders.show', ['id' => encrypt($order->id)]);
        // return redirect()->route('all_orders.index');
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

    private function createCombinedOrder(User $rep, array $shippingAddress)
    {
        return CombinedOrder::create([
            'user_id' => $rep->id,
            'shipping_address' => json_encode($shippingAddress),
        ]);
    }

    private function createOrder(Request $request, CombinedOrder $combinedOrder, User $customer, User $rep)
    {
        return Order::create([
            'combined_order_id' => $combinedOrder->id,
            'user_id' => $rep->id,
            'seller_id' => 9,
            'additional_info' => $request->additional_info,
            'delivery_date' => $request->delivery_date,
            'shipping_address' => $combinedOrder->shipping_address,
            'shipping_type' => 'home_delivery',
            'order_from' => 'web',
            'company_address' => $rep->company_address,
            'company_shipping_address' => $rep->shipping_address,
            'company_tax_number' => $rep->tax_number,
            'member_serial' => $customer->member_serial,
            'payment_type' => 'cash_on_delivery',
            'delivery_viewed' => '0',
            'payment_status_viewed' => '0',
            'code' => now()->format('Ymd-His') . rand(10, 99),
            'date' => now()->timestamp,
            'payment_status' => 'unpaid',
            'by_rep' => true,
            'for_customer' => $customer->id,
        ]);
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

    private function calculateOrderProfits(Order $order,)
    {
        $order->profits = $order->get_order_details_profits();
        $order->discount_percent = 100 - (($order->get_order_details_price() / $order->get_order_details_original_price()) * 100);
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
        if ($product->name != 'shipping' && $quantity > $productStock->qty) {
            $order->delete();
            $combinedOrder->delete();
            abort(400, translate('The requested quantity is not available for ') . $product->name);
        }

        $productStock->decrement('qty', (int)$quantity);
    }

    private function updateUserStats(User $rep, User $customer, $orderProfits)
    {
        $rep->update([
            'last_order_at' => now()->toDateString(),
            'profits' => $rep->profits + $orderProfits,
        ]);

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
}
