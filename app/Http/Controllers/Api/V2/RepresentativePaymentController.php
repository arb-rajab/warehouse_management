<?php

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\RepresentativePayment;
use App\Http\Resources\V2\RepresentativePaymentCollection;

class RepresentativePaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $payment_query = RepresentativePayment::query()->where('rep_id', $user->id);

            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%';

                // Search in the current table fields
                $payment_query->where(function ($query) use ($searchTerm) {
                    $query->where('receipt_code', 'like', $searchTerm);
                });

                // Use whereHas to query related 'rep' and 'customer' names
                $payment_query->orWhereHas('customer', function ($query) use ($searchTerm) {
                    $query->where('name', 'like', $searchTerm);
                })->orWhereHas('rep', function ($query) use ($searchTerm) {
                    $query->where('name', 'like', $searchTerm)
                    ->orWhere('AccSysID', 'like', $searchTerm);
                });
            }

        if(!$user->is_rep){
            return response()->json([
                'message' => 'You cannot do this action, you are not representative',
                'result' => false
            ], 403);
        }

        $payments = $payment_query->orderBy('created_at', 'desc')
            ->paginate(10);

        return new RepresentativePaymentCollection($payments);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if(!$user->is_rep){
            return response()->json([
                'message' => 'You cannot do this action, you are not representative',
                'result' => false
            ], 403);
        }


        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|required_without_all:customer_otajer_id',
            'customer_otajer_id' => 'nullable|required_without_all:customer_id',
            // 'receipt_code' => 'required|integer',
            'invoice_code' => 'nullable|integer',
            'notes' => 'nullable|string',
            'amount' => 'required|integer',
            'date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->all()
            ]);
        }

        $customer = $request->customer_id ? User::find($request->customer_id) : User::where('AccSysId', $request->customer_otajer_id)->first();
        if(!$customer){
            return response()->json([
                'message' => 'User not found',
                'result' => false
            ], 404);
        }

        $formattedDate = \Carbon\Carbon::now()->format('Ymd');
        $receiptCode = $formattedDate . '-' . $customer->AccSysID . '-' . $request->amount;

        $representativePayment = RepresentativePayment::create([
            'customer_id' => $customer->id,
            'otajer_id' => $customer->AccSysID,
            'rep_id' => auth()->user()->id,
            'customer_name' => $customer->name,
            'amount' => $request->amount,
            'receipt_code' => $receiptCode,
            'invoice_code' => $request->invoice_code,
            'notes' => $request->notes,
            'date' => now(),
        ]);

        return response()->json([
            'message' => 'Payment has been added successfully, and it is waiting for approval.',
            'result' => true,
            'pdf_link' => route('payments.export', ['id' => $representativePayment->id]),
            'qr' => route('api.rep.payment.generate_qr', ['id' => $representativePayment->id])
        ], 200);

    }

    public function generate_qr(Request $request)
    {
        $payment = RepresentativePayment::findOrFail($request->id);
        $svgString = \QrCode::size(200)->generate(route('payments.export', ['id' => $payment->id]));
        return response($svgString)
            ->header('Content-Type', 'image/svg+xml');
    }

}
