@extends('shop.frontend.layouts.user_panel')

@section('panel_content')
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="fs-20 fw-700 text-dark">{{ __('storefront.product.followed_sellers') }}</h1>
            </div>
        </div>
    </div>

    @if (count($followed_sellers) > 0)
        <div class="px-3">
            <div class="row row-cols-xxl-4 row-cols-xl-4 row-cols-lg-3 row-cols-md-2 row-cols-1 border-top border-left">
                @foreach ($followed_sellers as $key => $followed_seller)
                    @if($followed_seller->shop !=null)
                        <div class="col border-right border-bottom p-4 has-transition hov-shadow-out z-1" id="followed_seller_{{ $followed_seller->shop->id }}">
                            <!-- Shop logo -->
                            <a href="{{ route('shop.visit', $followed_seller->shop->slug) }}" class="d-flex mx-auto justify-content-center align-item-center h-130px w-130px  overflow-hidden hov-scale" tabindex="0" style="border: 1px solid #e5e5e5; border-radius: 50%; box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.06);">
                                <img src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                                    data-src="{{ uploaded_asset($followed_seller->shop->logo) }}"
                                    alt="{{ $followed_seller->shop->name }}"
                                    class="img-fit lazyload"
                                    onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder-rect.jpg') }}';">
                            </a>
                            <div class="text-center">
                                <!-- Shop name -->
                                <h2 class="fs-14 fw-700 text-dark text-truncate-2 h-40px mt-3">
                                    <a href="{{ route('shop.visit', $followed_seller->shop->slug) }}" class="text-reset hov-text-primary" tabindex="0">{{ $followed_seller->shop->name }}</a>
                                </h2>
                                <!-- Shop Rating -->
                                <div class="rating rating-md rating-space mt-2 mb-3">
                                    {{ renderStarRating($followed_seller->shop->rating) }}
                                    <span class="ml-1 fs-14">({{ $followed_seller->shop->num_of_reviews }}
                                        {{ __('storefront.product.reviews') }})</span>
                                </div>
                                <div class="mb-3">
                                    <a data-method="post" href="{{ route("followed_seller.remove", ['id'=>$followed_seller->shop->id]) }}" class="fs-12 fw-700 hov-text-warning">{{ __('storefront.product.unfollow_this_seller') }}</a>
                                </div>
                                <!-- Visit Button -->
                                <a href="{{ route('shop.visit', $followed_seller->shop->slug) }}" class="btn btn-light text-gray-dark btn-block btn-sm rounded-0 border fw-700" tabindex="0" style="padding: 0.75rem">
                                    {{ __('storefront.common.visit_store') }}
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        <div class="row">
            <div class="col">
                <div class="text-center bg-white p-4 border">
                    <img class="mw-100 h-200px" src="{{ static_asset('assets/img/nothing.svg') }}" alt="Image">
                    <h5 class="mb-0 h5 mt-3">{{ __('storefront.common.there_isn_t_anything_added_yet')}}</h5>
                </div>
            </div>
        </div>
    @endif
    <!-- Pagination -->
    <div class="aiz-pagination">
        {{ $followed_sellers->links() }}
    </div>
@endsection
