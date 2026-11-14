@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
    $align = $mailLocale === 'ar' ? 'right' : 'left';
    $endAlign = $mailLocale === 'ar' ? 'left' : 'right';
    $showPrices = \Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->admin_verified;
    $shipping_address = json_decode($order->shipping_address);
    $cell = 'padding:10px 8px;border-bottom:1px solid #ece5df;text-align:'.$align.';';
    $headCell = 'padding:8px;background-color:#f3eee9;color:#6b625c;font-size:12px;font-weight:bold;text-align:'.$align.';';
    $amount = 'direction:ltr;unicode-bidi:embed;text-align:'.$endAlign.';';
@endphp
@extends('shop.emails.layouts.brand')

@section('title', __('delivery.documents.order', [], $mailLocale))

@section('content')
    <h1 style="margin:0 0 4px;font-size:22px;line-height:1.3;font-weight:bold;color:#1f1a17;">{{ __('delivery.documents.order', [], $mailLocale) }}
        <span dir="ltr" style="direction:ltr;unicode-bidi:embed;color:#a11208;">{{ $order->code }}</span></h1>
    <p style="margin:0 0 18px;font-size:13px;color:#6b625c;">
        {{ __('storefront.checkout.order_date', [], $mailLocale) }}: {{ date('d-m-Y', $order->date) }}
    </p>

    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:0 0 18px;font-size:13px;color:#3d3530;">
        <tr><td style="padding:2px 0;font-weight:bold;color:#1f1a17;">{{ get_setting('site_name') }}</td></tr>
        <tr><td style="padding:2px 0;">{{ get_setting('contact_address') }}</td></tr>
        <tr><td style="padding:2px 0;">{{ __('storefront.auth.email', [], $mailLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ get_setting('contact_email') }}</span></td></tr>
        <tr><td style="padding:2px 0;">{{ __('storefront.auth.phone', [], $mailLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ get_setting('contact_phone') }}</span></td></tr>
    </table>

    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:0 0 18px;padding:12px 14px;background-color:#faf7f4;border:1px solid #ece5df;font-size:13px;color:#3d3530;">
        <tr><td style="padding:2px 14px;font-weight:bold;color:#6b625c;">{{ __('delivery.documents.order_for', [], $mailLocale) }}:</td></tr>
        <tr><td style="padding:2px 14px;">{{ __('storefront.auth.email', [], $mailLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $shipping_address->email }}</span></td></tr>
        <tr><td style="padding:2px 14px;">{{ __('storefront.auth.phone', [], $mailLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $shipping_address->phone }}</span></td></tr>
        <tr><td style="padding:2px 14px;">{{ __('storefront.common.company_address', [], $mailLocale) }} : {{ $order->company_address }}</td></tr>
        <tr><td style="padding:2px 14px;">{{ __('delivery.documents.company_shipping_address', [], $mailLocale) }} : {{ $order->company_shipping_address }}</td></tr>
        <tr><td style="padding:2px 14px;">{{ __('storefront.common.tax_number', [], $mailLocale) }} : <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $order->company_tax_number }}</span></td></tr>
    </table>

    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="font-size:13px;color:#1f1a17;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="{{ $headCell }}">{{ __('storefront.product.product_name', [], $mailLocale) }}</th>
                <th style="{{ $headCell }}" width="60">{{ __('storefront.product.qty', [], $mailLocale) }}</th>
                @if($showPrices)
                    <th style="{{ $headCell }}" width="90">{{ __('storefront.product.unit_price', [], $mailLocale) }}</th>
                    <th style="{{ $headCell }}" width="90">{{ __('storefront.common.total', [], $mailLocale) }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($order->orderDetails as $key => $orderDetail)
                @if ($orderDetail->product != null)
                    <tr>
                        <td style="{{ $cell }}font-weight:bold;">{{ $orderDetail->product->name . $orderDetail->product->ar_name }}
                            @if(($orderDetail->entry_unit ?? $orderDetail->variation) != null) ({{ $orderDetail->entry_unit ?? $orderDetail->variation }}) @endif
                        </td>
                        <td style="{{ $cell }}color:#6b625c;">{{ $orderDetail->quantity }}</td>
                        @if($showPrices)
                            <td style="{{ $cell }}{{ $amount }}color:#6b625c;">{{ single_price($orderDetail->price/$orderDetail->quantity) }}</td>
                            <td style="{{ $cell }}{{ $amount }}font-weight:bold;">{{ single_price($orderDetail->price+$orderDetail->tax) }}</td>
                        @endif
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if($showPrices)
        <table role="presentation" width="60%" align="{{ $endAlign }}" border="0" cellspacing="0" cellpadding="0" style="margin-top:16px;font-size:13px;">
            <tr>
                <td style="padding:4px 8px;color:#6b625c;text-align:{{ $align }};">{{ __('storefront.common.subtotal', [], $mailLocale) }}</td>
                <td style="padding:4px 8px;{{ $amount }}">{{ single_price($order->orderDetails->sum('price')) }}</td>
            </tr>
            <tr>
                <td style="padding:4px 8px;color:#6b625c;text-align:{{ $align }};">{{ __('seller.products.shipping_cost', [], $mailLocale) }}</td>
                <td style="padding:4px 8px;{{ $amount }}">{{ single_price($order->orderDetails->sum('shipping_cost')) }}</td>
            </tr>
            <tr>
                <td style="padding:8px;border-top:2px solid #c4170c;font-weight:bold;font-size:15px;text-align:{{ $align }};">{{ __('delivery.documents.grand_total', [], $mailLocale) }}</td>
                <td style="padding:8px;border-top:2px solid #c4170c;font-weight:bold;font-size:15px;color:#a11208;{{ $amount }}">{{ single_price($order->grand_total) }}</td>
            </tr>
        </table>
    @endif
@endsection
