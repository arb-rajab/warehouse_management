@php
    $offer = $active_offers->first();
    $offer_products = $offer->offer_products->load(['product:id,slug,name,ar_name,thumbnail_img,unit_price', 'product.product_translations:id,product_id,lang,name', 'product.taxes:id,product_id,tax,tax_type']);
@endphp
<div class="bg-white border">
    <div class="p-3 p-sm-4">
        <h3 class="fs-16 fw-700 mb-0">
            <span class="mr-4">{{ __('storefront.cart.offered_products') }}</span>
            <span class="mr-4">{{ __('storefront.common.quantitiy_required') . ":" . $offer->base_product->quantity }}</span>
        </h3>
    </div>
    <div class="px-4">
        <div class="aiz-carousel gutters-5 half-outside-arrow" data-items="5" data-xl-items="3"
            data-lg-items="4" data-md-items="3" data-sm-items="2" data-xs-items="2"
            data-arrows='true' data-infinite='true'>

            @foreach ($offer_products as $key => $offered_product)
                <div class="carousel-box">
                    <div class="aiz-card-box hov-shadow-md my-2 has-transition hov-scale-img">
                        <div class="">
                            <a href="{{ \App\Support\StorefrontUrl::to($offered_product->product) }}"
                                class="d-block">
                                <img class="img-fit lazyload mx-auto h-140px h-md-190px has-transition"
                                    src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                    data-src="{{ uploaded_asset($offered_product->product->thumbnail_img) }}"
                                    alt="{{ $offered_product->product->getTranslation('name') }}"
                                    onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                            </a>
                        </div>
                        <div class="p-md-3 p-2 text-center">
                            <h3 class="fw-400 fs-14 text-dark text-truncate-2 lh-1-4 mb-0 h-35px">
                                <a href="{{ \App\Support\StorefrontUrl::to($offered_product->product) }}"
                                    class="d-block text-reset hov-text-primary">{{ $offered_product->product->getTranslation('name') }}</a>
                            </h3>

                            @if(Auth::check() && Auth::user()->admin_verified)
                                <div class="fs-14 mt-3">
                                    <span class="fw-700 text-info">
                                        {{ __('storefront.common.you_will_get') . ": " . $offered_product->quantity }}
                                        @if ($offer->is_repeatable)
                                        {{ 'x ' . $offer->base_product?->product->getTranslation('name') . ' quantitity' }}
                                        @endif
                                    </span>

                                </div>
                                <div class="fs-14 mt-3">
                                    <span class="fw-700 text-primary">{{ offer_discount($offered_product) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
