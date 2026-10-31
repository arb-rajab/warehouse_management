@php
    $mailLocale = $array['locale'];
    $shippingAddress = json_decode($order->shipping_address);
@endphp
{{ __('delivery.documents.order', [], $mailLocale) }}: {!! $order->code !!}
{!! get_setting('site_name') !!}
{!! get_setting('contact_address') !!}
{{ __('storefront.auth.email', [], $mailLocale) }}: {!! get_setting('contact_email') !!}
{{ __('storefront.auth.phone', [], $mailLocale) }}: {!! get_setting('contact_phone') !!}
{{ __('storefront.checkout.order_date', [], $mailLocale) }}: {{ date('d-m-Y', $order->date) }}

{{ __('delivery.documents.order_for', [], $mailLocale) }}
{{ __('storefront.auth.email', [], $mailLocale) }}: {!! $shippingAddress?->email !!}
{{ __('storefront.auth.phone', [], $mailLocale) }}: {!! $shippingAddress?->phone !!}
{{ __('storefront.common.company_address', [], $mailLocale) }}: {!! $order->company_address !!}
{{ __('delivery.documents.company_shipping_address', [], $mailLocale) }}: {!! $order->company_shipping_address !!}
{{ __('storefront.common.tax_number', [], $mailLocale) }}: {!! $order->company_tax_number !!}
@foreach($order->orderDetails as $orderDetail)
@if($orderDetail->product != null)

{{ __('storefront.product.product_name', [], $mailLocale) }}: {!! $orderDetail->product->name . $orderDetail->product->ar_name !!} @if(($orderDetail->entry_unit ?? $orderDetail->variation) != null)({!! $orderDetail->entry_unit ?? $orderDetail->variation !!})@endif
{{ __('storefront.product.qty', [], $mailLocale) }}: {{ $orderDetail->quantity }}
@if(Auth::check() && Auth::user()->admin_verified)
{{ __('storefront.product.unit_price', [], $mailLocale) }}: {!! single_price($orderDetail->price / $orderDetail->quantity) !!}
{{ __('storefront.common.total', [], $mailLocale) }}: {!! single_price($orderDetail->price + $orderDetail->tax) !!}
@endif
@endif
@endforeach
@if(Auth::check() && Auth::user()->admin_verified)

{{ __('storefront.common.subtotal', [], $mailLocale) }}: {!! single_price($order->orderDetails->sum('price')) !!}
{{ __('seller.products.shipping_cost', [], $mailLocale) }}: {!! single_price($order->orderDetails->sum('shipping_cost')) !!}
{{ __('delivery.documents.grand_total', [], $mailLocale) }}: {!! single_price($order->grand_total) !!}
@endif
