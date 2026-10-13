<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkDeleteOrdersRequest;
use App\Http\Requests\Store\Admin\FilterOrdersRequest;
use App\Http\Requests\Store\Admin\UpdateOrderActionRequest;
use App\Http\Resources\Store\OrderResource;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\StoreUser;
use App\Services\StoreAdminOrderService;
use App\Utility\NotificationUtility;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller implements HasMiddleware
{
    public function __construct(private StoreAdminOrderService $orders) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_orders|view_inhouse_orders|view_seller_orders|view_pickup_point_orders', only: ['all_orders']),
            new Middleware('permission:view_order_details', only: ['show', 'details']),
            new Middleware('permission:delete_order', only: ['destroy', 'bulk_order_delete']),
            new Middleware('permission:update_order_delivery_status', only: ['update_delivery_status']),
            new Middleware('permission:update_order_payment_status', only: ['update_payment_status']),
            new Middleware('permission:update_order_tracking', only: ['update_tracking_code']),
            new Middleware('permission:update_order_notes', only: ['update_manager_notes']),
            new Middleware('permission:edit_order_price', only: ['edit_item_price']),
        ];
    }

    public function all_orders(FilterOrdersRequest $request): Response
    {
        $name = $request->route()->getName();
        $permission = match ($name) {
            'inhouse_orders.index' => 'view_inhouse_orders', 'seller_orders.index' => 'view_seller_orders',
            'pick_up_point.index' => 'view_pickup_point_orders', default => 'view_all_orders',
        };
        abort_unless($request->user('store')->can($permission), 403);
        $query = Order::query()->select(['id', 'code', 'invoice_number', 'user_id', 'for_customer', 'by_rep', 'delivery_date', 'delivery_status', 'payment_status', 'payment_type', 'commission_calculated', 'grand_total', 'profits', 'discount_percent', 'indicator', 'manager_notes', 'created_at'])->with(['user:id,name,AccSysID,rep_discount_percentage', 'rep:id,name,AccSysID']);
        $adminId = StoreUser::query()->where('user_type', 'admin')->orderBy('id')->value('id');
        if ($name === 'inhouse_orders.index') {
            $query->where('seller_id', $adminId);
        } elseif ($name === 'seller_orders.index') {
            $query->where('seller_id', '!=', $adminId);
        } elseif ($name === 'pick_up_point.index') {
            $query->where('shipping_type', 'pickup_point');
            $user = $request->user('store');
            $user->load('staff.pick_up_point:id,staff_id');
            $pickup = $user->staff?->pick_up_point?->id;
            abort_if($user->user_type === 'staff' && $pickup === null, 403);
            if ($pickup !== null) {
                $query->where('pickup_point_id', $pickup);
            }
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function (Builder $matches) use ($term): void {
                $matches->where('code', 'like', $term)->orWhere(function (Builder $customers) use ($term): void {
                    $customers->where('by_rep', 1)->whereHas('rep', fn (Builder $users) => $users->where('AccSysID', 'like', $term));
                })->orWhere(function (Builder $customers) use ($term): void {
                    $customers->where('by_rep', 0)->whereHas('user', fn (Builder $users) => $users->where('AccSysID', 'like', $term));
                });
            });
        }
        foreach (['payment_status', 'delivery_status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->validated($field));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->validated('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->validated('to'));
        }
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Sales/Orders/Index', [
            'orders' => $this->paginated(OrderResource::collection($query->orderByDesc('id')->paginate($perPage)->withQueryString())),
            'filters' => [...$request->only(['search', 'payment_status', 'delivery_status', 'from', 'to']), 'per_page' => $perPage], 'listRoute' => $name,
        ]);
    }

    public function show(string $order): Response
    {
        if (! ctype_digit($order)) {
            try {
                $order = (string) decrypt($order);
            } catch (DecryptException) {
                abort(404);
            }
        }
        abort_unless(ctype_digit($order), 404);
        $order = Order::query()->whereKey($order)->firstOrFail();
        $order->forceFill(['viewed' => 1])->save();
        $order->load(['user:id,name,AccSysID,rep_discount_percentage', 'rep:id,name,AccSysID', 'orderDetails:'.implode(',', OrderResource::DETAIL_COLUMNS)]);
        $address = json_decode($order->shipping_address ?? '[]', true);

        return Inertia::render('Store/Admin/Sales/Orders/Show', ['order' => new OrderResource($order),
            'additionalInfo' => $order->getAttribute('additional_info'), 'trackingCode' => $order->tracking_code,
            'shippingAddress' => is_array($address) ? array_intersect_key($address, array_flip(['name', 'email', 'address', 'country', 'state', 'city', 'postal_code', 'phone'])) : [],
            'couponDiscount' => (float) $order->coupon_discount]);
    }

    public function details(UpdateOrderActionRequest $request): RedirectResponse
    {
        return redirect()->route('all_orders.show', Order::query()->findOrFail($request->integer('order_id')));
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        DB::transaction(fn () => $this->deleteOrder($order));

        return $this->redirectPreservingQuery('all_orders.index', $request);
    }

    public function bulk_order_delete(BulkDeleteOrdersRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach (Order::query()->whereIn('id', $request->validated('id'))->orderBy('combined_order_id')->orderBy('id')->get() as $order) {
                $this->deleteOrder($order);
            }
        });

        return $this->redirectPreservingQuery('all_orders.index', $request);
    }

    private function deleteOrder(Order $found): void
    {
        $order = $this->orders->lock($found);
        if ($order->delivery_status === 'cancelled' && ($order->payment_status !== 'unpaid' || $order->getAttribute('commission_calculated'))) {
            throw ValidationException::withMessages(['action' => __('messages.order_not_editable')]);
        }
        if ($order->delivery_status !== 'cancelled') {
            $this->orders->assertEditable($order);
            foreach ($order->orderDetails()->orderBy('id')->lockForUpdate()->get() as $line) {
                $this->orders->restock($line);
            }
        }
        foreach ($order->orderDetails as $line) {
            Product::query()->whereKey($line->product_id)->decrement('num_of_sale', $line->quantity);
        }
        foreach (array_unique(array_filter([$order->user_id, $order->by_rep ? $order->for_customer : null])) as $id) {
            StoreUser::query()->whereKey($id)->decrement('profits', (float) $order->getAttribute('profits'));
        }
        $combinedId = $order->combined_order_id;
        $order->orderDetails()->delete();
        $order->delete();
        if ($combinedId !== null) {
            DB::table('combined_orders')->where('id', $combinedId)->update(['grand_total' => Order::query()->where('combined_order_id', $combinedId)->sum('grand_total')]);
        }
    }

    public function update_delivery_status(UpdateOrderActionRequest $request): RedirectResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id'));
        $status = $request->validated('status');
        DB::transaction(function () use ($order, $status): void {
            $locked = $this->orders->lock($order);
            if ($locked->delivery_status === $status) {
                return;
            }
            if (in_array($locked->delivery_status, ['cancelled', 'delivered'], true)) {
                throw ValidationException::withMessages(['action' => __('messages.order_terminal')]);
            }
            if ($status === 'cancelled') {
                foreach ($locked->orderDetails()->orderBy('id')->lockForUpdate()->get() as $line) {
                    $this->orders->restock($line);
                }
                if ($locked->payment_type === 'wallet') {
                    StoreUser::query()->whereKey($locked->user_id)->lockForUpdate()->increment('balance', $locked->grand_total);
                }
            }
            $locked->forceFill(['delivery_status' => $status, 'delivery_viewed' => 0])->save();
            $locked->orderDetails()->update(['delivery_status' => $status]);
            DB::afterCommit(fn () => $this->notifyTransition($locked, $status));
        });

        return redirect()->route('all_orders.show', $order);
    }

    public function update_payment_status(UpdateOrderActionRequest $request): RedirectResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id'));
        $status = $request->validated('status');
        DB::transaction(function () use ($order, $status): void {
            $locked = $this->orders->lock($order);
            if ($locked->payment_status === $status) {
                return;
            }
            if ($locked->delivery_status === 'cancelled' || ($locked->getAttribute('commission_calculated') && $status !== 'paid')) {
                throw ValidationException::withMessages(['action' => __('messages.order_terminal')]);
            }
            $locked->forceFill(['payment_status' => $status, 'payment_status_viewed' => 0])->save();
            $locked->orderDetails()->update(['payment_status' => $status]);
            if ($status === 'paid' && ! $locked->getAttribute('commission_calculated')) {
                $locked->load('user', 'shop', 'orderDetails.product.category', 'orderDetails.product.user.shop');
                calculateCommissionAffilationClubPoint($locked);
            }
            DB::afterCommit(fn () => $this->notifyTransition($locked, $status));
        });

        return redirect()->route('all_orders.show', $order);
    }

    private function notifyTransition(Order $order, string $status): void
    {
        $order->load('user:id,device_token');
        NotificationUtility::sendNotification($order, $status);
        if (get_setting('google_firebase') == 1 && $order->user?->getAttribute('device_token')) {
            NotificationUtility::sendOrderFirebaseNotification($order, __('messages.order_updated_title'), __('messages.order_updated_body', ['code' => $order->code]));
        }
    }

    public function update_tracking_code(UpdateOrderActionRequest $request): RedirectResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id'));
        $order->tracking_code = $request->validated('tracking_code');
        $order->save();

        return redirect()->route('all_orders.show', $order);
    }

    public function update_manager_notes(UpdateOrderActionRequest $request): RedirectResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id', $request->integer('id')));
        $order->setAttribute('manager_notes', $request->validated('notes'))->save();

        return redirect()->route('all_orders.show', $order);
    }

    public function edit_item_price(UpdateOrderActionRequest $request): RedirectResponse
    {
        $line = OrderDetail::query()->findOrFail($request->integer('id'));
        $order = Order::query()->findOrFail($line->order_id);
        DB::transaction(function () use ($order, $line, $request): void {
            $locked = $this->orders->lock($order);
            $this->orders->assertEditable($locked);
            $item = OrderDetail::query()->lockForUpdate()->findOrFail($line->id);
            $oldProfit = (float) $locked->getAttribute('profits');
            $margin = $item->price > 0 ? (float) $item->getAttribute('profits') / $item->price : (float) Product::query()->with('collection:id,amount')->whereKey($item->product_id)->first()?->collection?->getAttribute('amount') / 100;
            $item->forceFill(['price' => round((float) $request->validated('admin_price') * $item->quantity, 2), 'is_price_changed' => true]);
            $item->setAttribute('profits', round($item->price * $margin, 2))->save();
            $this->orders->recalculate($locked);
            foreach (array_unique(array_filter([$locked->user_id, $locked->by_rep ? $locked->for_customer : null])) as $id) {
                StoreUser::query()->whereKey($id)->increment('profits', (float) $locked->getAttribute('profits') - $oldProfit);
            }
        });

        return redirect()->route('all_orders.show', $order);
    }
}
