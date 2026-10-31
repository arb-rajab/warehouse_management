@extends('shop.seller.layouts.app')

@section('panel_content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ __('storefront.product.product_queries') }}</h5>
        </div>
        <div class="card-body">
            <table class="table aiz-table mb-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th data-breakpoints="lg">#</th>
                        <th>{{ __('seller.products.user_name') }}</th>
                        <th>{{ __('storefront.product.product_name') }}</th>
                        <th data-breakpoints="lg">{{ __('seller.products.question') }}</th>
                        <th data-breakpoints="lg">{{ __('seller.products.reply') }}</th>
                        <th>{{ __('storefront.common.status') }}</th>
                        <th class="text-right">{{ __('storefront.common.options') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($queries as $key => $query)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ translate($query->user->name) }}</td>
                            <td>{{ translate($query->product->name) }}</td>
                            <td>{{ translate(Str::limit($query->question, 100)) }}</td>
                            <td>{{ translate(Str::limit($query->reply, 100)) }}</td>
                            <td>
                                <span
                                    class="badge badge-inline {{ $query->reply == null ? 'badge-warning' : 'badge-success' }}">
                                    {{ $query->reply == null ? __('seller.products.not_replied') : __('seller.products.replied') }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                                    href="{{ route('seller.product_query.show', encrypt($query->id)) }}"
                                    title="{{ __('seller.products.view') }}">
                                    <i class="las la-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $queries->appends(request()->input())->links() }}
            </div>
        </div>
    </div>
@endsection

@section('modal')
    @include('shop.modals.delete_modal')
@endsection
