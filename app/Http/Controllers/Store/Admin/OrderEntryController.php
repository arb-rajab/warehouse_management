<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\SearchOrderOptionsRequest;
use App\Http\Requests\Store\Admin\StoreOrderRequest;
use App\Http\Requests\Store\Admin\UpdateOrderRequest;
use App\Http\Resources\Store\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StoreUser;
use App\Services\StoreAdminOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderEntryController extends Controller implements HasMiddleware
{
    public function __construct(private StoreAdminOrderService $orders) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:add_order', only: ['create', 'store', 'shippingCreate', 'shippingStore']),
            new Middleware('permission:edit_order', only: ['edit', 'update']),
            new Middleware('permission:add_order|edit_order', only: ['search'])];
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Sales/Orders/Create', ['representativeOrder' => true, 'submissionKey' => (string) Str::uuid()]);
    }

    public function shippingCreate(): Response
    {
        return Inertia::render('Store/Admin/Sales/Orders/Create', ['representativeOrder' => false, 'submissionKey' => (string) Str::uuid()]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->validated(), $request->user('store')->id, true);

        return redirect()->route('all_orders.show', $order);
    }

    public function shippingStore(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->validated(), $request->user('store')->id, false);

        return redirect()->route('all_orders.show', $order);
    }

    public function edit(Order $order): Response
    {
        $this->orders->assertEditable($order);
        abort_if($order->by_rep, 403);
        $order->load('user:id,name,AccSysID,rep_discount_percentage', 'rep:id,name,AccSysID', 'orderDetails:'.implode(',', OrderResource::DETAIL_COLUMNS));

        return Inertia::render('Store/Admin/Sales/Orders/Edit', ['order' => new OrderResource($order),
            'additionalInfo' => $order->getAttribute('additional_info'), 'couponDiscount' => (float) $order->coupon_discount]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orders->update($order, $request->validated());

        return redirect()->route('all_orders.show', $order);
    }

    public function search(SearchOrderOptionsRequest $request): JsonResponse
    {
        $q = $request->string('q')->toString();
        if ($request->validated('kind') !== 'products') {
            $users = StoreUser::query()->select(['id', 'name', 'AccSysID', 'member_serial', 'rep_discount_percentage'])
                ->where('user_type', 'customer')->where('is_rep', $request->validated('kind') === 'representatives')
                ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $names) => $names->where('name', 'like', '%'.$q.'%')->orWhere('AccSysID', 'like', '%'.$q.'%')->orWhere('member_serial', 'like', '%'.$q.'%')))
                ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();

            return response()->json($this->paginated(JsonResource::collection($users->through(fn (StoreUser $user) => $user->only(['id', 'name', 'AccSysID', 'member_serial', 'rep_discount_percentage'])))));
        }
        $products = Product::query()->select(['id', 'name', 'ar_name', 'serial', 'mat_id', 'unit_price', 'unit_equal', 'tax', 'digital'])
            ->where('published', 1)->where('approved', 1)
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $names) => $names->where('name', 'like', '%'.$q.'%')->orWhere('ar_name', 'like', '%'.$q.'%')->orWhere('serial', 'like', '%'.$q.'%')))
            ->with(['stocks' => fn ($query) => $query->select(['id', 'product_id', 'variant', 'price', 'qty'])->orderBy('id')])
            ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();

        return response()->json($this->paginated(JsonResource::collection($products->through(fn (Product $product) => [
            'id' => $product->id, 'name' => app()->getLocale() === 'ar' ? ($product->ar_name ?: $product->name) : $product->name,
            'serial' => $product->getAttribute('serial'), 'mat_id' => $product->getAttribute('mat_id'),
            'unit_price' => (float) $product->getAttribute('unit_price'), 'unit_equal' => (float) $product->getAttribute('unit_equal'),
            'tax' => (float) $product->getAttribute('tax'), 'digital' => (bool) $product->getAttribute('digital'),
            'stocks' => $product->stocks->map(fn (ProductStock $stock) => ['variant' => $stock->variant, 'price' => (float) $stock->price, 'qty' => (int) $stock->qty])->all(),
        ]))));
    }
}
