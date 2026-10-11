<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterRepresentativePaymentsRequest;
use App\Http\Resources\Store\RepresentativePaymentResource;
use App\Models\RepresentativePayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use PDF;
use QrCode;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RepresentativePaymentController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_representative_payments', only: ['index']),
            new Middleware('permission:approve_representative_payment', only: ['accept', 'reject']),
            new Middleware('permission:export_representative_payment', only: ['export']),
        ];
    }

    public function index(FilterRepresentativePaymentsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $payments = RepresentativePayment::query()->select(['id', 'amount', 'receipt_code', 'invoice_code', 'rep_id', 'customer_id', 'admin_id', 'customer_name', 'otajer_id', 'notes', 'status', 'date'])
            ->with(['rep:id,name', 'customer:id,name,AccSysID', 'admin:id,name']);
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $payments->where(function (Builder $query) use ($search) {
                $query->where('invoice_code', 'like', '%'.$search.'%')->orWhere('receipt_code', 'like', '%'.$search.'%')
                    ->orWhere('amount', $search)->orWhereHas('rep', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('admin', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'));
            });
        }
        $statuses = array_values(array_filter(['accepted', 'rejected', 'pending'], fn (string $status) => $request->boolean($status)));
        if ($statuses !== []) {
            $payments->whereIn('status', $statuses);
        }

        return Inertia::render('Store/Admin/Representatives/Payments/Index', [
            'payments' => $this->paginated(RepresentativePaymentResource::collection($payments->orderByDesc('created_at')->orderBy('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'search' => $request->input('search', ''), 'accepted' => $request->boolean('accepted'), 'rejected' => $request->boolean('rejected'), 'pending' => $request->boolean('pending')],
        ]);
    }

    public function accept(Request $request, RepresentativePayment $payment): RedirectResponse
    {
        return $this->decide($request, $payment, 'accepted');
    }

    public function reject(Request $request, RepresentativePayment $payment): RedirectResponse
    {
        return $this->decide($request, $payment, 'rejected');
    }

    public function export(RepresentativePayment $payment): HttpResponse
    {
        $payment->load(['rep:id,name', 'customer:id,name,AccSysID', 'admin:id,name']);
        $qr = QrCode::size(200)->generate(URL::signedRoute('payments.show', ['id' => $payment->id]));

        return PDF::loadView('shop.frontend.user.export-rep-payments', ['payment' => $payment, 'qr' => $qr])->download('payment-'.$payment->id.'.pdf');
    }

    private function decide(Request $request, RepresentativePayment $payment, string $status): RedirectResponse
    {
        DB::transaction(function () use ($request, $payment, $status) {
            $payment = RepresentativePayment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->status === 'pending', 409);
            $payment->update(['status' => $status, 'admin_id' => $request->user('store')->id]);
        });

        return $this->redirectPreservingQuery('admin.rep.payments.index', $request);
    }
}
