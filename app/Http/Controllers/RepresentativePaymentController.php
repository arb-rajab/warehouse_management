<?php

namespace App\Http\Controllers;

use App\Models\RepresentativePayment;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PDF;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use QrCode;

class RepresentativePaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = RepresentativePayment::where('rep_id', auth()->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return view('frontend.user.rep-payments', compact('payments'));
    }

    public function create()
    {
        $customers = \App\Models\User::where('user_type', 'customer')->where('is_rep', false)->get();
        return view('frontend.user.rep-payments-create', compact('customers'));
    }
    public function show($id)
    {
        $payment = RepresentativePayment::findOrFail($id);

        return view('frontend.user.rep-payments-show', compact('payment'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->is_rep) {
            flash('You cannot do this action, you are not representative')->error();
            return redirect()->route('payments.index');
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|required_without_all:customer_otajer_id',
            'customer_otajer_id' => 'nullable|required_without_all:customer_id',
            // 'receipt_code' => 'required',
            'invoice_code' => 'nullable',
            'notes' => 'nullable|string',
            'amount' => 'required|integer',
            'date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $customer = $request->customer_id ? User::find($request->customer_id) : User::where('AccSysId', $request->customer_otajer_id)->first();

        $formattedDate = \Carbon\Carbon::now()->format('Ymd');
        $receiptCode = $formattedDate . '-' . $customer->AccSysID . '-' . $request->amount;

        $payment = RepresentativePayment::create([
            'customer_id' => $customer->id,
            'otajer_id' => $customer->AccSysID,
            'rep_id' => auth()->user()->id,
            'customer_name' => $customer->name,
            'amount' => $request->amount,
            'receipt_code' => $receiptCode,
            'invoice_code' => $request->invoice_code,
            'notes' => $request->notes,
            'date' => $request->date ?? now(),
        ]);

        flash('Payment Created Successfully, and it is waiting for approval.')->success();
        return redirect()->route('payments.index');
    }

    public function generate_qr(Request $request)
    {
        $payment = RepresentativePayment::findOrFail($request->id);
        return QrCode::size(200)->generate(route('payments.export', ['id' => $payment->id]));

    }

    public function generate_qr_to_show_payment(Request $request)
    {
        $payment = RepresentativePayment::findOrFail($request->id);
        return QrCode::size(200)->generate(route('payments.show', ['id' => $payment->id]));

    }

    public function export($id)
    {
        $payment = RepresentativePayment::findOrFail($id);
        $qrCode = $this->generate_qr_to_show_payment(new Request(['id' => $id]));

        $pdf = PDF::loadView('frontend.user.export-rep-payments', [
            'payment' => $payment,
            'qr' => $qrCode,
        ]);

        return $pdf->download('payment-' . $payment->receipt_code . '.pdf');
    }

    public function shareByWhatsapp($id)
    {
        $payment = RepresentativePayment::findOrFail($id);

        if ($payment->customer) {
            $formattedPhoneNumber = $payment->customer->phone;

            if(!$formattedPhoneNumber){
                flash('The customer did not add a phone number')->error();
                return redirect()->route('payments.index');
            }

            $phoneUtil = PhoneNumberUtil::getInstance();
            $numberProto = $phoneUtil->parse($formattedPhoneNumber, 'NL');
            $formattedPhoneNumber = $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
            $paymentLink = route('payments.export', ['id' => $payment->id]);

            $url = isset($formattedPhoneNumber) ? 'https://wa.me/' . str_replace(' ', '', $formattedPhoneNumber) . '?text=Thank%20you%20for%20your%20payment!%0A%0AClick%20here%20to%20view%20your%20payment%20details:%0A' . $paymentLink : null;

            return redirect($url);
        }else{
            flash('Customer not found')->error();
            return redirect()->route('payments.index');
        }
    }
}
