@extends('shop.seller.layouts.app')

@section('panel_content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ __('storefront.checkout.payment_history') }}</h5>
        </div>
        @if (count($payments) > 0)
            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('storefront.common.date')}}</th>
                            <th>{{ __('storefront.common.amount')}}</th>
                            <th>{{ __('storefront.checkout.payment_method')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $key => $payment)
                            <tr>
                                <td>
                                    {{ $key+1 }}
                                </td>
                                <td>{{ date('d-m-Y', strtotime($payment->created_at)) }}</td>
                                <td>
                                    {{ single_price($payment->amount) }}
                                </td>
                                <td>
                                    {{ \Illuminate\Support\Facades\Lang::has('storefront.status.'.$payment->payment_method) ? \App\Support\StoreLocale::status($payment->payment_method) : ucfirst(str_replace('_', ' ', $payment->payment_method)) }} @if ($payment->txn_code != null) ({{  __('seller.orders.trx_id') }} : {{ $payment->txn_code }}) @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                	{{ $payments->links() }}
              	</div>
            </div>
        @endif
    </div>

@endsection
