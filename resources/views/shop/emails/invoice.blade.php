@php
    $mailLocale = $mailLocale ?? ($array['locale'] ?? \App\Support\StoreLocale::deliveryLocale());
@endphp
<html lang="{{ $mailLocale }}" dir="{{ $mailLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('delivery.documents.order', [], $mailLocale) }}</title>
    <meta http-equiv="Content-Type" content="text/html;"/>
    <meta charset="UTF-8">
	<style media="all">
		@font-face {
            font-family: Arial, 'DejaVu Sans', sans-serif;
            src: url("{{ static_asset('fonts/Roboto-Regular.ttf') }}") format("truetype");
            font-weight: normal;
            font-style: normal;
        }
        *{
            margin: 0;
            padding: 0;
            line-height: 1.3;
            font-family: Arial, 'DejaVu Sans', sans-serif;
            color: #333542;
        }
		body{
			font-size: .875rem;
            direction: {{ $mailLocale === 'ar' ? 'rtl' : 'ltr' }};
		}
		.gry-color *,
		.gry-color{
			color:#878f9c;
		}
		table{
			width: 100%;
		}
		table th{
			font-weight: normal;
		}
		table.padding th{
			padding: .5rem .7rem;
		}
		table.padding td{
			padding: .7rem;
		}
		table.sm-padding td{
			padding: .2rem .7rem;
		}
		.border-bottom td,
		.border-bottom th{
			border-bottom:1px solid #eceff4;
		}
		.text-left{
			text-align:left;
		}
		.text-right{
			text-align:right;
		}
		.small{
			font-size: .85rem;
		}
		.currency{
            direction: ltr;
            unicode-bidi: embed;
		}
	</style>
</head>
<body>
	<div>
		@php
			$logo = get_setting('header_logo');
		@endphp
		<div style="background: #eceff4;padding: 1.5rem;">
			<table>
				<tr>
					<td>
						@if($logo != null)
							<img loading="lazy"  src="{{ uploaded_asset($logo) }}" height="40" style="display:inline-block;">
						@else
							<img loading="lazy"  src="{{ static_asset('assets/img/logo.png') }}" height="40" style="display:inline-block;">
						@endif
					</td>
				</tr>
			</table>
			<table>
				<tr>
					<td style="font-size: 1.2rem;" class="strong">{{ get_setting('site_name') }}</td>
					<td class="text-right"></td>
				</tr>
				<tr>
					<td class="gry-color small">{{ get_setting('contact_address') }}</td>
					<td class="text-right"></td>
				</tr>
				<tr>
					<td class="gry-color small">{{  __('storefront.auth.email', [], $mailLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ get_setting('contact_email') }}</span></td>
					<td class="text-right small"><span class="gry-color small">{{  __('storefront.checkout.order_id', [], $mailLocale) }}:</span> <span class="strong"><span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $order->code }}</span></span></td>
				</tr>
				<tr>
					<td class="gry-color small">{{  __('storefront.auth.phone', [], $mailLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ get_setting('contact_phone') }}</span></td>
					<td class="text-right small"><span class="gry-color small">{{  __('storefront.checkout.order_date', [], $mailLocale) }}:</span> <span class=" strong">{{ date('d-m-Y', $order->date) }}</span></td>
				</tr>
			</table>

		</div>

		<div style="padding: 1.5rem;padding-bottom: 0">
            <table>
				@php
					$shipping_address = json_decode($order->shipping_address);
				@endphp
				<tr><td class="strong small gry-color">{{ __('delivery.documents.order_for', [], $mailLocale) }}:</td></tr>
				{{-- <tr><td class="strong">{{ $shipping_address->name }}</td></tr>
				<tr><td class="gry-color small">{{ $shipping_address->address }}, {{ $shipping_address->city }}, {{ $shipping_address->country }}</td></tr> --}}
				<tr><td class="gry-color small">{{ __('storefront.auth.email', [], $mailLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $shipping_address->email }}</span></td></tr>
				<tr><td class="gry-color small">{{ __('storefront.auth.phone', [], $mailLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $shipping_address->phone }}</span></td></tr>

                <hr>

                <tr><td>{{ __('storefront.common.company_address', [], $mailLocale) }} : {{ $order->company_address }}</td></tr>
                <tr><td>{{ __('delivery.documents.company_shipping_address', [], $mailLocale) }} : {{ $order->company_shipping_address }}</td></tr>
                <tr><td>{{ __('storefront.common.tax_number', [], $mailLocale) }} : <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $order->company_tax_number }}</span></td></tr>
			</table>
		</div>

	    <div style="padding: 1.5rem;">
			<table class="padding text-left small border-bottom">
				<thead>
	                <tr class="gry-color" style="background: #eceff4;">
	                    <th width="35%">{{ __('storefront.product.product_name', [], $mailLocale) }}</th>

					{{-- 	<th width="15%">{{ __('storefront.checkout.delivery_type', [], $mailLocale) }}</th>  --}}
	                    <th width="10%">{{ __('storefront.product.qty', [], $mailLocale) }}</th>
                        @if(Auth::check() && Auth::user()->admin_verified)
	                    <th width="10%">{{ __('storefront.product.unit_price', [], $mailLocale) }}</th>
	                    {{-- <th width="10%">{{ __('storefront.common.tax', [], $mailLocale) }}</th> --}}
	                    <th width="10%" class="text-right">{{ __('storefront.common.total', [], $mailLocale) }}</th>
                        @endif
	                </tr>
				</thead>
				<tbody class="strong">
	                @foreach ($order->orderDetails as $key => $orderDetail)
		                @if ($orderDetail->product != null)
							<tr class="">
								<td>  {{ $orderDetail->product->name . $orderDetail->product->ar_name }}
                                    @if(($orderDetail->entry_unit ?? $orderDetail->variation) != null) ({{ $orderDetail->entry_unit ?? $orderDetail->variation }}) @endif
                                    <br>
                                </td>

								{{--
                                <td>
									@if ($order->shipping_type != null && $order->shipping_type == 'home_delivery')
										{{ __('storefront.checkout.home_delivery', [], $mailLocale) }}
									@elseif ($order->shipping_type == 'pickup_point')
										@if ($order->pickup_point != null)
											{{ $order->pickup_point->getTranslation('name') }} ({{ __('storefront.common.pickip_point', [], $mailLocale) }})
										@endif
									@endif
								</td>
                                 --}}
								<td class="gry-color">{{ $orderDetail->quantity }}</td>
                                @if(Auth::check() && Auth::user()->admin_verified)
								<td class="gry-color currency">{{ single_price($orderDetail->price/$orderDetail->quantity) }}</td>
								{{-- <td class="gry-color currency">{{ single_price($orderDetail->tax/$orderDetail->quantity) }}</td> --}}
			                    <td class="text-right currency">{{ single_price($orderDetail->price+$orderDetail->tax) }}</td>
                                @endif
							</tr>
		                @endif
					@endforeach
	            </tbody>
			</table>
		</div>

        @if(Auth::check() && Auth::user()->admin_verified)
	    <div style="padding:0 1.5rem;">
	        <table style="width: 40%;margin-left:auto;" class="text-right sm-padding small strong">
		        <tbody>
			        <tr>
			            <th class="gry-color text-left">{{ __('storefront.common.subtotal', [], $mailLocale) }}</th>
			            <td class="currency">{{ single_price($order->orderDetails->sum('price')) }}</td>
			        </tr>
			        <tr>
			            <th class="gry-color text-left">{{ __('seller.products.shipping_cost', [], $mailLocale) }}</th>
			            <td class="currency">{{ single_price($order->orderDetails->sum('shipping_cost')) }}</td>
			        </tr>
			        {{-- <tr class="border-bottom">
			            <th class="gry-color text-left">{{ __('storefront.common.tax', [], $mailLocale) }}</th>
			            <td class="currency">{{ single_price($order->orderDetails->sum('tax')) }}</td>
			        </tr> --}}
                    {{--
                    <tr class="border-bottom">
			            <th class="gry-color text-left">{{ __('storefront.cart.coupon', [], $mailLocale) }}</th>
			            <td class="currency">{{ single_price($order->coupon_discount) }}</td>
			        </tr>
                     --}}
			        <tr>
			            <th class="text-left strong">{{ __('delivery.documents.grand_total', [], $mailLocale) }}</th>
			            <td class="currency">{{ single_price($order->grand_total) }}</td>
			        </tr>
		        </tbody>
		    </table>
	    </div>
        @endif

	</div>
</body>
</html>
