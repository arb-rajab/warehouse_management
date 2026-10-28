@php
    $documentLocale = $documentLocale ?? \App\Support\StoreLocale::documentLocale();
@endphp
<html lang="{{ $documentLocale }}" dir="{{ $documentLocale === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('delivery.documents.order', [], $documentLocale) }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="UTF-8">
    <style media="all">
        @page {
            margin: 0;
            padding: 0;
        }

        body {
            font-size: 0.875rem;
            font-family: {{ $documentLocale === 'ar' ? 'dejavusans' : $font_family }};
            font-weight: normal;
            direction: {{ $documentLocale === 'ar' ? 'rtl' : 'ltr' }};
            text-align: {{ $documentLocale === 'ar' ? 'right' : 'left' }};
            padding: 0;
            margin: 0;
        }

        .currency {
            direction: ltr;
            unicode-bidi: embed;
        }

        .gry-color *,
        .gry-color {
            color: #000;
        }

        table {
            width: 100%;
        }

        table th {
            font-weight: normal;
        }

        table.padding th {
            padding: .25rem .7rem;
        }

        table.padding td {
            padding: .25rem .7rem;
        }

        table.sm-padding td {
            padding: .1rem .7rem;
        }

        .border-bottom td,
        .border-bottom th {
            border-bottom: 1px solid #eceff4;
        }

        .text-left {
            text-align: {{ $documentLocale === 'ar' ? 'right' : 'left' }};
        }

        .text-right {
            text-align: {{ $documentLocale === 'ar' ? 'left' : 'right' }};
        }
    </style>
</head>

<body>
    <div>

        @php
            $logo = get_setting('header_logo');
        @endphp

        <div style="background: #eceff4;padding: 1rem;">
            <table>
                <tr>
                    <td>
                        @if ($logo != null)
                            <img src="{{ uploaded_asset($logo) }}" height="30" style="display:inline-block;">
                        @else
                            <img src="{{ static_asset('assets/img/logo.png') }}" height="30"
                                style="display:inline-block;">
                        @endif
                    </td>
                    <td style="font-size: 1.5rem;" class="text-right strong">{{ __('delivery.documents.order', [], $documentLocale) }}:
                        <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $order->code }}</span></td>
                </tr>
            </table>
            <table>
                <tr>
                    <td style="font-size: 1rem;" class="strong">{{ get_setting('site_name') }}</td>
                    <td class="text-right"></td>
                </tr>
                <tr>
                    <td class="gry-color small">{{ get_setting('contact_address') }}</td>
                    <td class="text-right"></td>
                </tr>
                <tr>
                    <td class="gry-color small">{{ __('storefront.auth.email', [], $documentLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ get_setting('contact_email') }}</span></td>
                    {{-- <td class="text-right small"><span class="gry-color small">{{  __('storefront.checkout.order_id', [], $documentLocale) }}:</span> <span class="strong"><span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $order->code }}</span></span></td> --}}
                </tr>
                <tr>
                    <td class="gry-color small">{{ __('storefront.auth.phone', [], $documentLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ get_setting('contact_phone') }}</span></td>
                    <td class="text-right small"><span class="gry-color small">{{ __('storefront.checkout.order_date', [], $documentLocale) }}:</span>
                        <span class=" strong">{{ date('d-m-Y', $order->date) }}</span>
                    </td>
                </tr>
                <tr>
                    @isset($order->order_from)
                        <td class="gry-color small">{{ __('delivery.documents.order_from', [], $documentLocale) }}: {{ $order->order_from }}</td>
                    @endisset
                </tr>

                {{-- <tr>
					<td class="gry-color small"></td>
					<td class="text-right small">
                        <span class="gry-color small">
                            {{  __('storefront.checkout.payment_method', [], $documentLocale) }}:
                        </span>
                        <span class="strong">
                            {{ \App\Support\StoreLocale::paymentMethod($order->payment_type) }}
                        </span>
                    </td>
				</tr> --}}
            </table>

        </div>

        <div class="text-right" style="padding: 1rem;padding-bottom: 0">
            <table>
                @php
                    $shipping_address = json_decode($order->shipping_address);
                @endphp
                @if ($shipping_address)
                    <tr>
                        <td class="text-right strong small gry-color">{{ __('delivery.documents.order_for', [], $documentLocale) }}</td>
                    </tr>
                    <tr>
                        <td class="text-right strong">{{ __('storefront.common.name', [], $documentLocale) }}: {{ $shipping_address->name }}</td>
                    </tr>
                    {{-- <tr><td class="gry-color small">{{ $shipping_address->address }}, {{ $shipping_address->city }},  @if (isset(json_decode($order->shipping_address)->state)) {{ json_decode($order->shipping_address)->state }} - @endif {{ $shipping_address->postal_code }}, {{ $shipping_address->country }}</td></tr> --}}
                    <tr>
                        <td class="text-right gry-color small">{{ __('storefront.auth.email', [], $documentLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $shipping_address->email }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-right gry-color small">{{ __('storefront.auth.phone', [], $documentLocale) }}: <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $shipping_address->phone }}</span>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="text-right">{{ __('delivery.documents.otajer_order_id', [], $documentLocale) }} : <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $order->otajerOrderID }}</span></td>
                </tr>
                <tr>
                    <td class="text-right">{{ __('storefront.common.company_address', [], $documentLocale) }} : {{ $order->company_address }}</td>
                </tr>
                <hr>
                <tr>
                    <td class="text-right">{{ __('storefront.common.company_address', [], $documentLocale) }} : {{ $order->company_address }}</td>
                </tr>
                <tr>
                    @isset($order->delivery_date)
                        <td class="text-right">{{ __('storefront.checkout.delivery_date', [], $documentLocale) }} :
                            {{ date('d-m-Y', strtotime($order->delivery_date)) }}</td>
                    @endisset
                </tr>
                <tr>
                    <td class="text-right">{{ __('delivery.documents.company_shipping_address', [], $documentLocale) }} :
                        {{ $order->company_shipping_address }}</td>
                </tr>
                <tr>
                    <td class="text-right">{{ __('storefront.common.tax_number', [], $documentLocale) }} : <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $order->company_tax_number }}</span></td>
                </tr>
                @if ($order->by_rep)
                    <tr>
                        <td class="text-right"> {{ __('storefront.common.by_representative', [], $documentLocale) }}
                            {{ $order->user ? $order->user->name : __('delivery.documents.deleted_account', [], $documentLocale) }}</td>
                    </tr>
                    <tr>
                        <td class="text-right">{{ __('storefront.common.for_customer', [], $documentLocale) }}
                            {{ $order->rep ? $order->rep->name : __('delivery.documents.deleted_account', [], $documentLocale) }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <div style="padding: 1rem;">
            <table class="padding text-left small border-bottom">
                <thead>
                    <tr class="gry-color" style="background: #eceff4;">
                        <th width="7%" class="text-left">#</th>
                        <th width="35%" class="text-left">{{ __('storefront.product.product_name', [], $documentLocale) }}</th>
                        <th width="10%" class="text-left">{{ __('storefront.common.variation', [], $documentLocale) }}</th>
                        <th width="10%" class="text-left">{{ __('delivery.documents.types', [], $documentLocale) }}</th>
                        {{-- <th width="10%" class="text-left">{{ __('storefront.common.unit', [], $documentLocale) }}</th> --}}
                        <th width="10%" class="text-left">{{ __('delivery.documents.material_id', [], $documentLocale) }}</th>
                        <th width="10%" class="text-left">{{ __('storefront.common.item_notes', [], $documentLocale) }}</th>
                        {{-- <th width="10%" class="text-left">{{ __('storefront.checkout.delivery_type', [], $documentLocale) }}</th> --}}
                        <th width="7%" class="text-left">{{ __('storefront.product.qty', [], $documentLocale) }}</th>
                        <th width="7%" class="text-left">{{ __('storefront.common.tax', [], $documentLocale) }}</th>
                        <th width="10%" class="text-left">{{ __('delivery.documents.single_price', [], $documentLocale) }}</th>
                        <th width="10%" class="text-right">{{ __('storefront.common.total', [], $documentLocale) }}</th>
                    </tr>
                </thead>
                <tbody class="strong">
                    @php
                        $counter = 0;
                    @endphp
                    @foreach ($order->orderDetails as $key => $orderDetail)

                        <tr class="">
                            <td>
                                {{ $counter += 1 }}
                            </td>

                            <td>
                                @if ($orderDetail->product != null)
                                    {{ $orderDetail->product->name . $orderDetail->product->ar_name }}
                                @else
                                    <strong>{{ __('delivery.documents.product_deleted', [], $documentLocale) }}</strong>
                                @endif
                                <br>
                            </td>

                            <td>
                                @if (($orderDetail->entry_unit ?? $orderDetail->variation) != null)
                                    ({{ ($orderDetail->entry_unit ?? $orderDetail->variation) }})
                                @endif
                            </td>

                            <td>
                                @if ($orderDetail->product != null)
                                    @if (isset($cartResults[$orderDetail->product->serial]))
                                        @foreach ($cartResults[$orderDetail->product->serial] as $key => $cart)
                                            <div class="fw-400 fs-16">
                                                {{ $key . ' : ' . $cart }}

                                                {{-- {{ $cart['productVariation']['name'] . ' : ' . $cart['value'] }} --}}
                                            </div>
                                        @endforeach
                                    @endif
                                @endif
                            </td>

                            {{-- <td>{{ $orderDetail->product->unit }}</td> --}}

                            <td>

                                <span dir="ltr" style="direction:ltr;unicode-bidi:embed;">{{ $orderDetail->product_matId }}</span>

                            </td>

                            <td>

                                {{ $orderDetail->item_notes }}

                            </td>
                            {{-- <td>
									@if ($order->shipping_type != null && $order->shipping_type == 'home_delivery')
										{{ __('storefront.checkout.home_delivery', [], $documentLocale) }}
									@elseif ($order->shipping_type == 'pickup_point')
										@if ($order->pickup_point != null)
											{{ $order->pickup_point->getTranslation('name') }} ({{ __('storefront.common.pickip_point', [], $documentLocale) }})
										@else
                                            {{ __('storefront.checkout.pickup_point', [], $documentLocale) }}
										@endif
									@elseif ($order->shipping_type == 'carrier')
										@if ($order->carrier != null)
											{{ $order->carrier->name }} ({{ __('storefront.checkout.carrier', [], $documentLocale) }})
											<br>
											{{ __('storefront.common.transit_time', [], $documentLocale).' - '.$order->carrier->transit_time }}
										@else
											{{ __('storefront.checkout.carrier', [], $documentLocale) }}
										@endif
									@endif
								</td> --}}
                            <td class="">{{ $orderDetail->quantity }}</td>
                            <td>
                                @if ($orderDetail->product != null)
                                    {{ $orderDetail->product->tax }}%
                                @endif
                            </td>
                            <td class="currency">{{ single_price($orderDetail->price / $orderDetail->quantity) }}
                            </td>

                            {{-- <td class="currency">{{ single_price($orderDetail->tax/$orderDetail->quantity) }}</td> --}}
                            <td class="text-right currency">{{ single_price($orderDetail->price) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding:0 1.5rem;">
            <table class="text-right sm-padding small strong">
                <thead>
                    <tr>
                        <th width="60%"></th>
                        <th width="40%"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left">
                            @php
                                $removedXML = '<?xml version="1.0" encoding="UTF-8"?>';
                            @endphp
                            {!! str_replace($removedXML, '', QrCode::size(100)->generate($order->code)) !!}
                        </td>
                        <td>
                            <table class="text-right sm-padding small strong">
                                <tbody>
                                    {{-- <tr>
							            <th class="gry-color text-left">{{ __('storefront.common.subtotal', [], $documentLocale) }}</th>
							            <td class="currency">{{ single_price($order->orderDetails->sum('price')) }}</td>
							        </tr> --}}
                                    {{-- <tr>
							            <th class="gry-color text-left">{{ __('seller.products.shipping_cost', [], $documentLocale) }}</th>
							            <td class="currency">{{ single_price($order->orderDetails->sum('shipping_cost')) }}</td>
							        </tr> --}}
                                    {{-- <tr class="border-bottom">
							            <th class="gry-color text-left">{{ __('storefront.common.tax', [], $documentLocale) }}</th>
							            <td class="currency">{{ single_price($order->orderDetails->sum('tax')) }}</td>
							        </tr> --}}
                                    {{-- <tr class="border-bottom">
							            <th class="gry-color text-left">{{ __('storefront.cart.coupon_discount', [], $documentLocale) }}</th>
							            <td class="currency">{{ single_price($order->coupon_discount) }}</td>
							        </tr> --}}
                                    <tr>
                                        <th class="text-left strong">{{ __('delivery.documents.grand_total', [], $documentLocale) }}</th>
                                        <td class="currency">{{ single_price($order->grand_total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</body>

</html>
