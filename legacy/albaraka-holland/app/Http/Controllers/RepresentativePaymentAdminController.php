<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RepresentativePayment;
use PDF;
use QrCode;

class RepresentativePaymentAdminController extends Controller
{
    public function index(Request $request){

        $payments = RepresentativePayment::query();


        if ($request->has('search')){
            $sort_search = $request->search;
            $payments->where(function ($q) use ($sort_search){
                $q->where('invoice_code', 'like', '%' . $sort_search . '%')
                ->orWhere('receipt_code', 'like', '%' . $sort_search . '%')
                ->orWhere('amount', $sort_search)
                ->orWhereHas('rep', function ($item) use ($sort_search){
                    $item->where('name', 'like', '%' . $sort_search . '%');
                })
                ->orWhereHas('customer', function ($item) use ($sort_search){
                    $item->where('name', 'like', '%' . $sort_search . '%');
                })
                ->orWhereHas('admin', function ($item) use ($sort_search){
                    $item->where('name', 'like', '%' . $sort_search . '%');
                });
            });
        }

        $accepted = null;
        $rejected = null;
        $pending = null;

        if($request->has('accepted')){
            $accepted = true;
        }

        if($request->has('rejected')){
            $rejected = true;
        }

        if($request->has('pending')){
            $pending = true;
        }


        if($accepted){
            $payments->where('status', 'accepted');
        }

        if ($rejected){
            $payments->orWhere('status', 'rejected');
        }

        if ($pending){
            $payments->orWhere('status', 'pending');
        }

        $payments = $payments
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('backend.representatives.payments.index', compact('payments'));

    }

    public function accept($id)
    {
        $payment = RepresentativePayment::findOrFail($id);
        if ($payment->status != 'pending') {
            flash('This payment has already been ' . $payment->status)->error();
            return redirect()->route('admin.rep.payments.index');
        }

        $payment->update([
            'status' => 'accepted',
            'admin_id' => auth()->user()->id
        ]);

        flash('The Payment was successfully Accepted')->success();
        return redirect()->route('admin.rep.payments.index');
    }

    public function reject($id)
    {
        $payment = RepresentativePayment::findOrFail($id);
        if ($payment->status != 'pending') {
            flash('This payment has already been ' . $payment->status)->error();
            return redirect()->route('admin.rep.payments.index');
        }

        $payment->update([
            'status' => 'rejected',
            'admin_id' => auth()->user()->id
        ]);

        flash('The Payment was successfully Rejected')->success();
        return redirect()->route('admin.rep.payments.index');
    }

    public function export($id)
    {
        $payment = RepresentativePayment::findOrFail($id);
        $qrCode = $this->generate_qr_to_show_payment(new Request(['id' => $id]));

        return PDF::loadView('frontend.user.export-rep-payments', [
            'payment' => $payment,
            'qr' => $qrCode,
        ])->download('payment-' . $payment->id . '.pdf');
    }

    public function generate_qr_to_show_payment(Request $request)
    {
        $payment = RepresentativePayment::findOrFail($request->id);
        return QrCode::size(200)->generate(route('payments.show', ['id' => $payment->id]));

    }
}
