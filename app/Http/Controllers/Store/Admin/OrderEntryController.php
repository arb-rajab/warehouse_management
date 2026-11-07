<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\SearchOrderOptionsRequest;
use App\Http\Requests\Store\Admin\StoreOrderRequest;
use App\Http\Requests\Store\Admin\UpdateOrderRequest;
use App\Http\Resources\Store\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreUser;
use App\Services\StoreAdminOrderService;
use App\Support\SearchOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderEntryController extends Controller implements HasMiddleware
{
    use FlashesSuccess;
    use SearchesOptions;

    /** The columns of a customer or representative option (App\Support\SearchOption). */
    private const array PERSON_COLUMNS = ['id', 'name', 'email', 'phone', 'AccSysID', 'member_serial', 'rep_discount_percentage'];

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
        $this->flashSuccess('order_has_been_created_successfully');

        return redirect()->route('all_orders.show', $order);
    }

    public function shippingStore(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->validated(), $request->user('store')->id, false);
        $this->flashSuccess('order_has_been_created_successfully');

        return redirect()->route('all_orders.show', $order);
    }

    public function edit(Order $order): Response
    {
        $this->orders->assertEditable($order);
        abort_if($order->by_rep, 403);
        $order->load('user:'.implode(',', self::PERSON_COLUMNS), 'rep:id,name,AccSysID', 'orderDetails:'.implode(',', OrderResource::DETAIL_COLUMNS));

        return Inertia::render('Store/Admin/Sales/Orders/Edit', ['order' => new OrderResource($order),
            'customerOption' => $order->user === null ? null : SearchOption::customer($order->user),
            'additionalInfo' => $order->getAttribute('additional_info'), 'couponDiscount' => (float) $order->coupon_discount]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orders->update($order, $request->validated());
        $this->flashSuccess('order_has_been_updated_successfully');

        return redirect()->route('all_orders.show', $order);
    }

    /**
     * The typeahead behind the order form's customer, representative and product
     * selects (StoreSearchSelect): one page of `App\Support\SearchOption` rows.
     */
    public function search(SearchOrderOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $kind = $request->validated('kind');

        if ($kind === 'products') {
            $products = Product::query()->select(['id', 'name', 'ar_name', 'serial', 'unit_price', 'unit_equal', 'tax', 'digital', 'thumbnail_img'])
                ->where('published', 1)->where('approved', 1)
                ->with(['thumbnailUpload:id,file_name,external_link', 'stocks' => fn ($query) => $query->select(['id', 'product_id', 'variant', 'price', 'qty'])->orderBy('id')]);
            if ($term !== '') {
                $this->whereWordsMatch($products, ctype_digit($term) ? ['name', 'ar_name', 'serial', 'id'] : ['name', 'ar_name', 'serial'], $term);
            }
            $this->orderByRelevance($products, 'name', $term);

            return $this->optionsPage($products, fn (Product $product): array => SearchOption::orderProduct($product));
        }

        $users = StoreUser::query()->select(self::PERSON_COLUMNS)
            ->where('user_type', 'customer')->where('is_rep', $kind === 'representatives');
        if ($term !== '') {
            $this->whereWordsMatch($users, ['name', 'email', 'phone', 'AccSysID', 'member_serial'], $term);
        }
        $this->orderByRelevance($users, 'name', $term);

        return $this->optionsPage($users, fn (StoreUser $user): array => $kind === 'representatives' ? SearchOption::representative($user) : SearchOption::customer($user));
    }
}
