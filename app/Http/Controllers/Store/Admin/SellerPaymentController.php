<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterSellerPayoutRequest;
use App\Http\Requests\Store\Admin\SellerReferenceRequest;
use App\Http\Requests\Store\Admin\StoreSellerPaymentRequest;
use App\Http\Resources\Store\SellerPaymentResource;
use App\Http\Resources\Store\SellerPayoutResource;
use App\Models\Payment;
use App\Models\SellerWithdrawRequest;
use App\Models\Shop;
use App\Models\StoreUser;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellerPaymentController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:pay_to_seller', only: ['modal', 'store']),
            new Middleware('permission:seller_payment_history', only: ['index', 'show']),
        ];
    }

    public function index(FilterSellerPayoutRequest $request): Response
    {
        return $this->history($request);
    }

    public function show(FilterSellerPayoutRequest $request, StoreUser $seller): Response
    {
        $seller->load('shop:id,user_id,name');

        return $this->history($request, $seller);
    }

    private function history(FilterSellerPayoutRequest $request, ?StoreUser $seller = null): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $payments = Payment::query()->select(['id', 'seller_id', 'amount', 'payment_method', 'txn_code', 'created_at'])
            ->with(['seller:id,name', 'seller.shop:id,user_id,name'])
            ->when($seller !== null, fn (Builder $query) => $query->where('seller_id', $seller->id))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
        // Blade hid orphan rows after pagination. Keep the same total and window.
        $payments->setCollection($payments->getCollection()->filter(fn (Payment $payment) => $payment->seller?->shop !== null)->values());

        return Inertia::render('Store/Admin/Sellers/Payments/Index', [
            'payments' => $this->paginated(SellerPaymentResource::collection($payments)),
            'seller' => $seller === null ? null : ['id' => $seller->id, 'name' => $seller->name, 'shop_name' => $seller->shop?->name],
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function modal(SellerReferenceRequest $request): JsonResponse
    {
        $shop = Shop::query()->select(SellerPayoutResource::COLUMNS)->findOrFail($request->integer('id'));

        return response()->json(['shop' => (new SellerPayoutResource($shop))->resolve($request), 'withdrawal' => null]);
    }

    public function store(StoreSellerPaymentRequest $request): RedirectResponse
    {
        $data = [
            'shop_id' => $request->validated('shop_id'),
            'amount' => $request->validated('amount'),
            'payment_method' => $request->validated('payment_option'),
            'payment_withdraw' => $request->validated('payment_withdraw'),
            'withdraw_request_id' => $request->validated('withdraw_request_id'),
            'txn_code' => $request->validated('txn_code'),
        ];
        $request->session()->put('payment_type', 'seller_payment');
        $request->session()->put('payment_data', $data);
        $outgoing = in_array($data['payment_method'], ['cash', 'bank_payment'], true);
        $withdrawal = $outgoing && $data['payment_withdraw'] === 'withdraw_request';

        try {
            DB::transaction(function () use ($request, $data, $outgoing, $withdrawal) {
                $shop = Shop::query()->lockForUpdate()->findOrFail($request->integer('shop_id'));
                $amount = BigDecimal::of((string) $data['amount']);
                $balance = BigDecimal::of((string) $shop->admin_to_pay);
                $limit = BigDecimal::max(0, $outgoing ? $balance : $balance->negated());
                if ($withdrawal) {
                    $pending = SellerWithdrawRequest::query()->lockForUpdate()
                        ->where('user_id', $shop->user_id)
                        ->where(fn (Builder $query) => $query->whereNull('status')->orWhere('status', 0))
                        ->findOrFail($request->integer('withdraw_request_id'));
                    if ($pending->amount === null || ! $amount->isEqualTo((string) $pending->amount)) {
                        throw ValidationException::withMessages([
                            'amount' => __('messages.seller_payout_full_request', ['amount' => $pending->amount]),
                        ]);
                    }
                }
                if ($amount->isGreaterThan($limit)) {
                    throw ValidationException::withMessages([
                        'amount' => __('messages.seller_payout_balance_limit', ['amount' => (string) $limit->strippedOfTrailingZeros()]),
                    ]);
                }
                if ($withdrawal) {
                    $pending->status = 1;
                    $pending->viewed = 1;
                    $pending->save();
                }
                if ($outgoing) {
                    $shop->decrement('admin_to_pay', $data['amount']);
                } else {
                    $shop->increment('admin_to_pay', $data['amount']);
                }
                $payment = new Payment;
                $payment->seller_id = $shop->user_id;
                $payment->amount = $data['amount'];
                $payment->payment_method = $outgoing ? $data['payment_method'] : 'Seller paid to admin';
                $payment->txn_code = $data['txn_code'];
                $payment->payment_details = null;
                $payment->save();
            });
        } finally {
            $request->session()->forget(['payment_data', 'payment_type']);
        }

        return redirect()->route($withdrawal ? 'withdraw_requests_all' : 'sellers.index', $request->query());
    }
}
