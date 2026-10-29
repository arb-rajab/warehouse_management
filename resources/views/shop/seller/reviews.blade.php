@extends('shop.seller.layouts.app')

@section('panel_content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ __('seller.common.product_reviews') }}</h5>
        </div>
        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('storefront.product.product')}}</th>
                        <th data-breakpoints="lg">{{ __('storefront.common.customer')}}</th>
                        <th>{{ __('storefront.product.rating')}}</th>
                        <th data-breakpoints="lg">{{ __('storefront.common.comment')}}</th>
                        <th data-breakpoints="lg">{{ __('storefront.common.published')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reviews as $key => $value)
                        @php
                            $review = \App\Models\Review::find($value->id);
                        @endphp
                        @if($review != null && $review->product != null && $review->user != null)
                            <tr>
                                <td>
                                    {{ $key+1 }}
                                </td>
                                <td>
                                    <a href="{{ \App\Support\StorefrontUrl::to($review->product) }}" target="_blank">{{  $review->product->getTranslation('name') }}</a>
                                </td>
                                <td>{{ $review->user->name }}</td>
                                <td>
                                    <span class="rating rating-sm">
                                        @for ($i=0; $i < $review->rating; $i++)
                                            <i class="las la-star active"></i>
                                        @endfor
                                        @for ($i=0; $i < 5-$review->rating; $i++)
                                            <i class="las la-star"></i>
                                        @endfor
                                    </span>
                                </td>
                                <td>{{ $review->comment }}</td>
                                <td>
                                    @if ($review->status == 1)
                                        <span class="badge badge-inline badge-success">{{  __('storefront.common.published') }}</span>
                                    @else
                                        <span class="badge badge-inline badge-danger">{{  __('seller.common.unpublished') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $reviews->links() }}
          	</div>
        </div>
    </div>

@endsection
