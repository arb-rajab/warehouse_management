<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkDeleteSellersRequest;
use App\Http\Requests\Store\Admin\FilterSellerRequest;
use App\Http\Requests\Store\Admin\SellerActionRequest;
use App\Http\Requests\Store\Admin\SellerReferenceRequest;
use App\Http\Requests\Store\Admin\StoreSellerRequest;
use App\Http\Requests\Store\Admin\UpdateSellerApprovalRequest;
use App\Http\Requests\Store\Admin\UpdateSellerRequest;
use App\Http\Resources\Store\SellerResource;
use App\Http\Resources\Store\UploadResource;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\StoreUser;
use App\Models\Upload;
use App\Notifications\EmailVerificationNotification;
use App\Services\ShopVerificationStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SellerController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_seller', only: ['index', 'verification', 'verificationFile']),
            new Middleware('permission:view_seller_profile', only: ['profile']),
            new Middleware('permission:login_as_seller', only: ['login']),
            new Middleware('permission:edit_seller', only: ['create', 'store', 'edit', 'update', 'approved', 'approve', 'reject']),
            new Middleware('permission:delete_seller', only: ['destroy', 'bulkDestroy']),
            new Middleware('permission:ban_seller', only: ['ban']),
        ];
    }

    public function index(FilterSellerRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $sellers = Shop::query()->select(['id', 'user_id', 'name', 'verification_status', 'verification_info', 'admin_to_pay'])
            ->has('user')
            ->with(['user' => fn (Relation $query) => $query->select(['id', 'name', 'email', 'phone', 'banned'])->withCount('products')])
            ->when($request->has('search'), fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->where('user_type', 'seller')
                ->where(fn (Builder $search) => $search->where('name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%'))))
            ->when($request->filled('approved_status'), fn (Builder $query) => $query->where('verification_status', $request->boolean('approved_status')))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Store/Admin/Sellers/Sellers/Index', [
            'sellers' => $this->paginated(SellerResource::collection($sellers)),
            'filters' => ['per_page' => $perPage, 'search' => $request->string('search')->toString(), 'approved_status' => $request->filled('approved_status') ? (int) $request->boolean('approved_status') : null],
            'payouts' => get_setting('Payouts_Section') == 1,
            'payment_links' => get_setting('Payouts_Section') == 1 || (bool) $request->user('store')?->raizer_admin,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Sellers/Sellers/Create');
    }

    public function store(StoreSellerRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = new StoreUser;
            $user->name = $request->validated('name');
            $user->email = $request->validated('email');
            $user->password = $request->validated('password');
            $user->user_type = 'seller';
            $user->save();
            if (get_setting('email_verification') != 1) {
                $user->email_verified_at = now();
            } else {
                $user->notify(new EmailVerificationNotification);
            }
            $user->save();
            $seller = new Seller;
            $seller->user_id = $user->id;
            $seller->save();
            $shop = new Shop;
            $shop->user_id = $user->id;
            $shop->slug = 'demo-shop-'.$user->id;
            $shop->save();
        });

        return redirect()->route('sellers.index');
    }

    public function edit(Shop $seller): Response
    {
        $seller->load('user:id,name,email');
        abort_if($seller->user === null, 404);

        return Inertia::render('Store/Admin/Sellers/Sellers/Edit', [
            'seller' => ['id' => $seller->id, 'name' => $seller->user->name, 'email' => $seller->user->email],
        ]);
    }

    public function update(UpdateSellerRequest $request, Shop $seller): RedirectResponse
    {
        $user = $seller->user;
        abort_if($user === null, 404);
        $user->name = $request->validated('name');
        $user->email = $request->validated('email');
        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }
        $user->save();

        return redirect()->route('sellers.index');
    }

    public function ban(SellerActionRequest $request, Shop $seller): RedirectResponse
    {
        $user = $seller->user;
        abort_if($user === null, 404);
        $user->banned = $user->banned == 1 ? 0 : 1;
        $user->save();

        return $this->redirectPreservingQuery('sellers.index', $request);
    }

    public function approve(SellerActionRequest $request, Shop $seller): RedirectResponse
    {
        $seller->verification_status = 1;
        $seller->save();
        Cache::forget('verified_sellers_id');

        return redirect()->route('sellers.index');
    }

    public function reject(SellerActionRequest $request, Shop $seller): RedirectResponse
    {
        $seller->verification_status = 0;
        $seller->verification_info = null;
        $seller->save();
        Cache::forget('verified_sellers_id');

        return redirect()->route('sellers.index');
    }

    public function approved(UpdateSellerApprovalRequest $request): RedirectResponse
    {
        $seller = Shop::query()->findOrFail($request->integer('id'));
        $seller->verification_status = (int) $request->boolean('status');
        $seller->save();
        Cache::forget('verified_sellers_id');

        return $this->redirectPreservingQuery('sellers.index', $request);
    }

    public function destroy(SellerActionRequest $request, Shop $seller): RedirectResponse
    {
        DB::transaction(fn () => $this->deleteSeller($seller));

        return $this->redirectPreservingQuery('sellers.index', $request);
    }

    public function bulkDestroy(BulkDeleteSellersRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach (Shop::query()->whereIn('id', $request->validated('id'))->get() as $seller) {
                $this->deleteSeller($seller);
            }
        });

        return $this->redirectPreservingQuery('sellers.index', $request);
    }

    private function deleteSeller(Shop $seller): void
    {
        Product::query()->where('user_id', $seller->user_id)->delete();
        OrderDetail::query()->whereIn('order_id', Order::query()->select('id')->where('user_id', $seller->user_id))->delete();
        Order::query()->where('user_id', $seller->user_id)->delete();
        StoreUser::destroy($seller->user_id);
        $seller->delete();
    }

    public function login(Shop $seller): RedirectResponse
    {
        abort_if($seller->user === null, 404);
        Auth::guard('store')->login($seller->user, true);

        return redirect()->route('seller.dashboard');
    }

    public function verification(Shop $seller): Response
    {
        $seller->load('user:id,name,email,address,phone');
        abort_if($seller->user === null, 404);
        /** @var list<array{label?: string, type?: string, value?: string}> $fields */
        $fields = json_decode($seller->verification_info ?? '[]', true) ?: [];
        $info = collect($fields)->map(function (array $field): array {
            $type = $field['type'] ?? '';
            $value = (string) ($field['value'] ?? '');

            return [
                'label' => (string) ($field['label'] ?? ''),
                'value' => $type === 'multi_select' ? implode(', ', json_decode($value, true) ?: []) : $value,
                'file_url' => $type === 'file' ? route('sellers.verification_file', ['name' => basename(str_replace('\\', '/', $value))]) : null,
            ];
        })->all();

        return Inertia::render('Store/Admin/Sellers/Sellers/Verification', [
            'seller' => ['id' => $seller->id, 'name' => $seller->name, 'address' => $seller->address, 'approved' => (bool) $seller->verification_status, 'has_verification' => $seller->verification_info !== null,
                'user' => $seller->user->only(['name', 'email', 'address', 'phone']), 'verification' => $info],
        ]);
    }

    public function verificationFile(string $name, ShopVerificationStorage $documents): BinaryFileResponse
    {
        abort_if(str_contains($name, '/') || str_contains($name, '\\') || in_array($name, ['.', '..'], true), 404);

        return $documents->download($name) ?? abort(404);
    }

    public function profile(SellerReferenceRequest $request): JsonResponse
    {
        $seller = Shop::query()->select(['id', 'user_id', 'name', 'slug', 'address', 'facebook', 'twitter', 'google', 'bank_name', 'bank_acc_name', 'bank_acc_no', 'bank_routing_no'])
            ->with(['user' => fn (Relation $query) => $query->select(['id', 'name', 'phone', 'balance', 'avatar_original'])->withCount('products')])->findOrFail($request->integer('id'));
        abort_if($seller->user === null, 404);
        $user = $seller->user;
        $social = [];
        foreach (['facebook', 'twitter', 'google'] as $key) {
            $url = $seller->getAttribute($key);
            $social[$key] = is_string($url) && preg_match('#^https?://#i', $url) ? $url : null;
        }

        $avatar = Upload::query()->select(['id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])->find($user->avatar_original);

        return response()->json([
            'id' => $seller->id, 'name' => $user->name, 'shop_name' => $seller->name, 'address' => $seller->address, 'phone' => $user->phone,
            'avatar_url' => $avatar === null ? null : (new UploadResource($avatar))->resolve($request)['url'], 'shop_url' => $seller->slug ? route('shop.visit', $seller->slug) : null, 'social' => $social,
            'bank_name' => $seller->bank_name, 'bank_acc_name' => $seller->bank_acc_name, 'bank_acc_no' => $seller->bank_acc_no, 'bank_routing_no' => $seller->bank_routing_no === null ? null : (string) $seller->bank_routing_no,
            'products_count' => (int) $user->getAttribute('products_count'), 'orders_count' => OrderDetail::query()->where('seller_id', $seller->user_id)->count(),
            'sold_amount' => single_price(OrderDetail::query()->where('seller_id', $seller->user_id)->whereIn('order_id', Order::query()->select('id')->where('payment_status', 'paid'))->sum('price')),
            'wallet_balance' => single_price($user->balance),
        ]);
    }
}
