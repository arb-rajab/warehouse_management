<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterSellerPayoutRequest;
use App\Http\Requests\Store\Admin\SellerWithdrawalPaymentRequest;
use App\Http\Requests\Store\Admin\SellerWithdrawalReferenceRequest;
use App\Http\Resources\Store\SellerPayoutResource;
use App\Http\Resources\Store\SellerWithdrawalResource;
use App\Models\SellerWithdrawRequest;
use App\Models\StoreUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SellerWithdrawRequestController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:view_seller_payout_requests')];
    }

    public function index(FilterSellerPayoutRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $withdrawals = SellerWithdrawRequest::query()->select(['id', 'user_id', 'amount', 'message', 'status', 'created_at'])
            ->with(['user:id,name', 'user.shop:id,user_id,name,admin_to_pay'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
        $withdrawals->setCollection($withdrawals->getCollection()->filter(fn (SellerWithdrawRequest $withdrawal) => $withdrawal->user?->shop !== null)->values());

        return Inertia::render('Store/Admin/Sellers/Withdrawals/Index', [
            'withdrawals' => $this->paginated(SellerWithdrawalResource::collection($withdrawals)),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function paymentModal(SellerWithdrawalPaymentRequest $request): JsonResponse
    {
        $user = StoreUser::query()->select(['id'])->with(['shop' => fn (Relation $query) => $query->select(SellerPayoutResource::COLUMNS)])->findOrFail($request->integer('id'));
        abort_if($user->shop === null, 404);
        $withdrawal = SellerWithdrawRequest::query()->select(['id', 'amount'])->findOrFail($request->integer('seller_withdraw_request_id'));

        return response()->json([
            'shop' => (new SellerPayoutResource($user->shop))->resolve($request),
            'withdrawal' => ['id' => $withdrawal->id, 'amount' => (float) $withdrawal->amount],
        ]);
    }

    public function messageModal(SellerWithdrawalReferenceRequest $request): JsonResponse
    {
        $withdrawal = SellerWithdrawRequest::query()->select(['id', 'message'])->findOrFail($request->integer('id'));

        return response()->json(['id' => $withdrawal->id, 'message' => $withdrawal->message]);
    }

    // These legacy resource actions were empty: retain their names and gate,
    // without inventing approval, rejection, message edits or deletion.
    public function create(): RedirectResponse
    {
        return redirect()->route('withdraw_requests_all');
    }

    public function show(SellerWithdrawRequest $withdrawRequest): RedirectResponse
    {
        return redirect()->route('withdraw_requests_all');
    }

    public function edit(SellerWithdrawRequest $withdrawRequest): RedirectResponse
    {
        return redirect()->route('withdraw_requests_all');
    }

    public function update(SellerWithdrawRequest $withdrawRequest): RedirectResponse
    {
        return redirect()->route('withdraw_requests_all');
    }

    public function destroy(SellerWithdrawRequest $withdrawRequest): RedirectResponse
    {
        return redirect()->route('withdraw_requests_all');
    }
}
