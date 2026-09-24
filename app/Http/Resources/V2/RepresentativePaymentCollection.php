<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use App\Models\RepresentativePayment;

class RepresentativePaymentCollection extends ResourceCollection
{
    public function generate_pdf_url($data)
    {
        return route('payments.export', $data->id);

    }
    public function generate_qr($data)
    {
        return route('api.rep.payment.generate_qr', ['id' => $data->id]);

    }
    public function generate_whatsapp_url($data)
    {
        try{
            if ($data->customer) {
                $formattedPhoneNumber = $data->customer->phone;

                if(!$formattedPhoneNumber){
                    return null;
                }

                $phoneUtil = PhoneNumberUtil::getInstance();
                $numberProto = $phoneUtil->parse($formattedPhoneNumber, 'NL');
                $formattedPhoneNumber = $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
                $paymentLink = route('payments.export', ['id' => $data->id]);

                $url = isset($formattedPhoneNumber) ? 'https://wa.me/' . str_replace(' ', '', $formattedPhoneNumber) . '?text=Thank%20you%20for%20your%20payment!%0A%0AClick%20here%20to%20view%20your%20payment%20details:%0A' . $paymentLink : null;

                return $url;
            }else{
                return null;
            }

        }catch(\Exception $e){
            \Log::error('Error generating WhatsApp URL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                return [
                    'id' => $data->id,
                    'amount' => $data->amount,
                    'customer_id' => $data->customer_id,
                    'customer_otajer_id' => !is_null($data->otajer_id) ? $data->otajer_id : $data->customer?->AccSysID,
                    'receipt_code' => $data->receipt_code,
                    'invoice_code' => $data->invoice_code,
                    'admin_name' => $data->admin?->name,
                    'customer_name' => $data->customer?->name,
                    'rep_name' => $data->rep?->name,
                    'notes' => $data->notes,
                    'pdf_url' => $this->generate_pdf_url($data),
                    'qr' => $this->generate_qr($data),
                    'whatsapp_url' => $this->generate_whatsapp_url($data),
                    'status' => $data->status,
                    'date' => $data->date,
                ];
            })
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }
}
