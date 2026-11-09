{{-- "Run a shop?" banner from the Direction A mock-up. Placeholder copy; links to the seller registration. --}}
<section class="sf-shop-banner-wrap">
    <div class="container">
        <div class="sf-shop-banner">
            <div class="sf-shop-banner-copy">
                <h2 class="sf-shop-banner-title">{{ __('storefront.home.shop_banner_title') }}</h2>
                <p class="sf-shop-banner-text">{{ __('storefront.home.shop_banner_text') }}</p>
            </div>
            <a class="btn sf-shop-banner-cta" href="{{ route('shops.create') }}">{{ __('storefront.home.shop_banner_cta') }}</a>
        </div>
    </div>
</section>
