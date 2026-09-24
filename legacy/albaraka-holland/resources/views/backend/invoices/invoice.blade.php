<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ translate('Order') }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="UTF-8">
    <style media="all">
        @page {
            margin: 0;
            padding: 0;
        }

        body {
            font-size: 0.875rem;
            font-family: '<?php echo $font_family; ?>';
            font-weight: normal;
            direction: <?php echo $direction; ?>;
            text-align: <?php echo $text_align; ?>;
            padding: 0;
            margin: 0;
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
            text-align: <?php echo $text_align; ?>;
        }

        .text-right {
            text-align: <?php echo $not_text_align; ?>;
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
                    <td style="font-size: 1.5rem;" class="text-right strong">{{ translate('ORDER') }}:
                        {{ $order->code }}</td>
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
                    <td class="gry-color small">{{ translate('Email') }}: {{ get_setting('contact_email') }}</td>
                    {{-- <td class="text-right small"><span class="gry-color small">{{  translate('Order ID') }}:</span> <span class="strong">{{ $order->code }}</span></td> --}}
                </tr>
                <tr>
                    <td class="gry-color small">{{ translate('Phone') }}: {{ get_setting('contact_phone') }}</td>
                    <td class="text-right small"><span class="gry-color small">{{ translate('Order Date') }}:</span>
                        <span class=" strong">{{ date('d-m-Y', $order->date) }}</span>
                    </td>
                </tr>
                <tr>
                    @isset($order->order_from)
                        <td class="gry-color small">{{ translate('Order From') }}: {{ $order->order_from }}</td>
                    @endisset
                </tr>

                {{-- <tr>
					<td class="gry-color small"></td>
					<td class="text-right small">
                        <span class="gry-color small">
                            {{  translate('Payment method') }}:
                        </span>
                        <span class="strong">
                            {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}
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
                        <td class="text-right strong small gry-color">{{ translate('Order For:') }}</td>
                    </tr>
                    <tr>
                        <td class="text-right strong">{{ translate('Name') }}: {{ $shipping_address->name }}</td>
                    </tr>
                    {{-- <tr><td class="gry-color small">{{ $shipping_address->address }}, {{ $shipping_address->city }},  @if (isset(json_decode($order->shipping_address)->state)) {{ json_decode($order->shipping_address)->state }} - @endif {{ $shipping_address->postal_code }}, {{ $shipping_address->country }}</td></tr> --}}
                    <tr>
                        <td class="text-right gry-color small">{{ translate('Email') }}: {{ $shipping_address->email }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-right gry-color small">{{ translate('Phone') }}: {{ $shipping_address->phone }}
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="text-right">{{ translate('Otajer Order Id') }} : {{ $order->otajerOrderID }}</td>
                </tr>
                <tr>
                    <td class="text-right">{{ translate('Company Address') }} : {{ $order->company_address }}</td>
                </tr>
                <hr>
                <tr>
                    <td class="text-right">{{ translate('Company Address') }} : {{ $order->company_address }}</td>
                </tr>
                <tr>
                    @isset($order->delivery_date)
                        <td class="text-right">{{ translate('Delivery Date') }} :
                            {{ date('d-m-Y', strtotime($order->delivery_date)) }}</td>
                    @endisset
                </tr>
                <tr>
                    <td class="text-right">{{ translate('Company Shipping Address') }} :
                        {{ $order->company_shipping_address }}</td>
                </tr>
                <tr>
                    <td class="text-right">{{ translate('Tax Number') }} : {{ $order->company_tax_number }}</td>
                </tr>
                @if ($order->by_rep)
                    <tr>
                        <td class="text-right"> {{ translate('By Representative') }} :
                            {{ $order->user ? $order->user->name : 'Deleted Account' }}</td>
                    </tr>
                    <tr>
                        <td class="text-right">{{ translate('For Customer') }} :
                            {{ $order->rep ? $order->rep->name : 'Deleted Account' }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <div style="padding: 1rem;">
            <table class="padding text-left small border-bottom">
                <thead>
                    <tr class="gry-color" style="background: #eceff4;">
                        <th width="7%" class="text-left">#</th>
                        <th width="35%" class="text-left">{{ translate('Product Name') }}</th>
                        <th width="10%" class="text-left">{{ translate('Variation') }}</th>
                        <th width="10%" class="text-left">{{ translate('Types') }}</th>
                        {{-- <th width="10%" class="text-left">{{ translate('Unit') }}</th> --}}
                        <th width="10%" class="text-left">{{ translate('Material ID') }}</th>
                        <th width="10%" class="text-left">{{ translate('Item Notes') }}</th>
                        {{-- <th width="10%" class="text-left">{{ translate('Delivery Type') }}</th> --}}
                        <th width="7%" class="text-left">{{ translate('QTY') }}</th>
                        <th width="7%" class="text-left">{{ translate('Tax') }}</th>
                        <th width="10%" class="text-left">{{ translate('Single Price') }}</th>
                        <th width="10%" class="text-right">{{ translate('Total') }}</th>
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
                                    <strong>{{ translate('Product Unavailable (Deleted)') }}</strong>
                                @endif
                                <br>
                            </td>

                            <td>
                                @if ($orderDetail->variation != null)
                                    ({{ $orderDetail->variation }})
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

                                {{ $orderDetail->product_matId }}

                            </td>

                            <td>

                                {{ $orderDetail->item_notes }}

                            </td>
                            {{-- <td>
									@if ($order->shipping_type != null && $order->shipping_type == 'home_delivery')
										{{ translate('Home Delivery') }}
									@elseif ($order->shipping_type == 'pickup_point')
										@if ($order->pickup_point != null)
											{{ $order->pickup_point->getTranslation('name') }} ({{ translate('Pickip Point') }})
										@else
                                            {{ translate('Pickup Point') }}
										@endif
									@elseif ($order->shipping_type == 'carrier')
										@if ($order->carrier != null)
											{{ $order->carrier->name }} ({{ translate('Carrier') }})
											<br>
											{{ translate('Transit Time').' - '.$order->carrier->transit_time }}
										@else
											{{ translate('Carrier') }}
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
							            <th class="gry-color text-left">{{ translate('Sub Total') }}</th>
							            <td class="currency">{{ single_price($order->orderDetails->sum('price')) }}</td>
							        </tr> --}}
                                    {{-- <tr>
							            <th class="gry-color text-left">{{ translate('Shipping Cost') }}</th>
							            <td class="currency">{{ single_price($order->orderDetails->sum('shipping_cost')) }}</td>
							        </tr> --}}
                                    {{-- <tr class="border-bottom">
							            <th class="gry-color text-left">{{ translate('Total Tax') }}</th>
							            <td class="currency">{{ single_price($order->orderDetails->sum('tax')) }}</td>
							        </tr> --}}
                                    {{-- <tr class="border-bottom">
							            <th class="gry-color text-left">{{ translate('Coupon Discount') }}</th>
							            <td class="currency">{{ single_price($order->coupon_discount) }}</td>
							        </tr> --}}
                                    <tr>
                                        <th class="text-left strong">{{ translate('Grand Total') }}</th>
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
