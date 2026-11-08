{{-- Direction A hero. Placeholder copy (lang storefront.home.*); no images. Shown only when no admin slider exists. --}}
<section class="sf-hero" aria-labelledby="sf-hero-title">
    <div class="container">
        <div class="sf-hero-card">
            <span class="sf-hero-eyebrow">{{ __('storefront.home.hero_eyebrow') }}</span>
            <h1 id="sf-hero-title" class="sf-hero-title">{{ __('storefront.home.hero_title') }}</h1>
            <p class="sf-hero-text">{{ __('storefront.home.hero_text') }}</p>
            <div class="sf-hero-actions">
                <a class="btn btn-primary sf-hero-cta" href="{{ route('search') }}">{{ __('storefront.home.hero_cta_primary') }}</a>
                <a class="btn sf-hero-ghost" href="{{ route('shops.create') }}">{{ __('storefront.home.hero_cta_secondary') }}</a>
            </div>
            <svg class="sf-hero-art" viewBox="0 0 200 200" aria-hidden="true" focusable="false">
                <circle cx="120" cy="120" r="80" fill="currentColor" opacity=".12" />
                <circle class="sf-hero-sun" cx="150" cy="70" r="26" fill="currentColor" />
                <path d="M60 170c10-50 50-80 100-80" stroke="currentColor" stroke-width="10" fill="none" stroke-linecap="round" opacity=".5" />
            </svg>
        </div>
    </div>
</section>
