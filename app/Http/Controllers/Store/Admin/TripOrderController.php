<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkUpdateTripOrdersRequest;
use App\Http\Requests\Store\Admin\FilterTripOrdersRequest;
use App\Http\Requests\Store\Admin\MarkTripOrderDeliveredRequest;
use App\Http\Requests\Store\Admin\UpdateTripOrderInvoiceRequest;
use App\Http\Resources\Store\TripCheckpointResource;
use App\Http\Resources\Store\TripOrderResource;
use App\Http\Resources\Store\TruckPhotoResource;
use App\Models\Order;
use App\Models\Upload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trip orders list: every order past confirmation that is not
 * cancelled, with its latest trip, the bulk "mark as confirmed / ready for
 * delivery" actions, the inline invoice number and "mark as delivered". The
 * retained Blade map picker marks an order delivered through the same
 * endpoint (`return_to=map`).
 */
class TripOrderController extends Controller implements HasMiddleware
{
    /** The delivery statuses the list never shows, as the Blade list hid them. */
    public const array HIDDEN_STATUSES = ['pending', 'cancelled'];

    /** The Blade list's delivery status filter choices. */
    public const array FILTER_STATUSES = ['confirmed', 'ready_for_delivery', 'on_delivery', 'delivered'];

    /** The statuses the bulk actions set, as the Blade bulk handler did. */
    public const array BULK_STATUSES = ['confirmed', 'ready_for_delivery'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_trips', only: ['index']),
            new Middleware('permission:edit_trip', only: ['bulkUpdateDeliveryStatus', 'updateInvoiceNumber', 'markDelivered']),
        ];
    }

    public function index(FilterTripOrdersRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');
        $customer = ['id', 'name', 'phone', 'company_name', 'AccSysID', 'shipping_address'];
        $orders = Order::query()
            ->select(['id', 'code', 'viewed', 'user_id', 'for_customer', 'by_rep', 'otajerOrderID', 'order_from', 'wms_status', 'shipping_address', 'invoice_number', 'additional_info', 'manager_notes', 'grand_total', 'delivery_status', 'delivery_date', 'payment_status', 'commission_calculated', 'created_at'])
            ->with([
                'user:'.implode(',', $customer),
                'rep:'.implode(',', $customer),
                'checkpoints' => fn (Relation $checkpoints) => $checkpoints
                    ->select(['id', 'trip_id', 'relationable_type', 'relationable_id', 'photos'])
                    ->orderBy('id'),
                'checkpoints.trip:id,code,driver_id,truck_id',
                'checkpoints.trip.driver:id,name',
                'checkpoints.trip.truck:id,name',
            ])
            ->withCount('orderDetails')
            ->whereNotIn('delivery_status', self::HIDDEN_STATUSES);
        if (is_string($search) && $search !== '') {
            $this->search($orders, '%'.$search.'%');
        }
        if ($request->filled('delivery_status')) {
            $orders->where('delivery_status', $request->validated('delivery_status'));
        }
        if ($request->filled('from')) {
            $orders->whereDate('created_at', '>=', $request->validated('from'));
        }
        if ($request->filled('to')) {
            $orders->whereDate('created_at', '<=', $request->validated('to'));
        }
        $orders = $orders->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
        $photoIds = array_values($orders->getCollection()
            ->flatMap(fn (Order $order) => TripCheckpointResource::uploadIds($order->checkpoints->last()?->photos))
            ->unique()->all());

        return Inertia::render('Store/Admin/Trips/Orders/Index', [
            'orders' => $this->paginated(TripOrderResource::collection($orders)),
            'photos' => TruckPhotoResource::collection(Upload::query()
                ->select(['id', 'file_original_name', 'file_name', 'external_link', 'type', 'file_size'])
                ->whereIn('id', $photoIds)->orderBy('id')->get()),
            'filters' => [
                'per_page' => $perPage,
                'search' => $search ?? '',
                'delivery_status' => $request->validated('delivery_status') ?? '',
                'from' => $request->validated('from') ?? '',
                'to' => $request->validated('to') ?? '',
            ],
            'deliveryStatuses' => self::FILTER_STATUSES,
            'bulkStatuses' => self::BULK_STATUSES,
        ]);
    }

    /**
     * The legacy bulk transition, unchanged: each selected order takes the
     * chosen status (confirmed or ready for delivery), whatever it had.
     */
    public function bulkUpdateDeliveryStatus(BulkUpdateTripOrdersRequest $request): RedirectResponse
    {
        $status = $request->string('delivery_status')->toString();
        DB::transaction(function () use ($request, $status): void {
            foreach (Order::query()->whereIn('id', $request->validated('id'))->orderBy('id')->lockForUpdate()->get() as $order) {
                $order->delivery_status = $status;
                $order->save();
            }
        });

        return $this->redirectToList($request);
    }

    /**
     * The legacy writer, unchanged: the invoice number is stored as given (an
     * empty field clears it) and the order goes out for delivery.
     */
    public function updateInvoiceNumber(UpdateTripOrderInvoiceRequest $request, Order $order): RedirectResponse
    {
        $order->invoice_number = $request->validated('invoice_number');
        $order->delivery_status = 'on_delivery';
        $order->save();

        return $this->redirectToList($request);
    }

    /**
     * The legacy writer, unchanged: the order is delivered. Returns to the
     * map picker for `return_to=map`, otherwise to the trip orders list.
     */
    public function markDelivered(MarkTripOrderDeliveredRequest $request, Order $order): RedirectResponse
    {
        $order->delivery_status = 'delivered';
        $order->save();

        if ($request->validated('return_to') === 'map') {
            return redirect()->route('trips.create-from-map');
        }

        return $this->redirectToList($request);
    }

    /**
     * The Blade list's search, grouped so it stays inside the list's
     * statuses: the order code, invoice number or shipping address, or the
     * customer (for a representative's order, the customer it was placed
     * for) by name, phone or accounting id. The Blade query searched a
     * `customer` relation the model does not have, so any search failed.
     *
     * @param  Builder<Order>  $orders
     */
    private function search(Builder $orders, string $term): void
    {
        $customer = fn (Builder $users) => $users->where(fn (Builder $users) => $users
            ->where('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('AccSysID', 'like', $term));
        $orders->where(fn (Builder $matches) => $matches
            ->where('code', 'like', $term)
            ->orWhere('invoice_number', 'like', $term)
            ->orWhere('shipping_address', 'like', $term)
            ->orWhere(fn (Builder $own) => $own->where('by_rep', false)->whereHas('user', $customer))
            ->orWhereHas('rep', $customer));
    }

    private function redirectToList(Request $request): RedirectResponse
    {
        return redirect()->route('trips.orders', Arr::except($request->query(), ['return_to']));
    }
}
