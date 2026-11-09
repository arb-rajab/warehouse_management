{{-- Four trust tiles from the Direction A mock-up. Placeholder copy (lang storefront.home.trust_*); inline SVG icons. --}}
@php
    // Literal keys, so StorefrontLocalizationTest can check each one in both catalogs.
    $trustTiles = [
        ['icon' => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z" /><circle cx="7" cy="17.5" r="1.6" /><circle cx="17" cy="17.5" r="1.6" />', 'title' => __('storefront.home.trust_1_title'), 'text' => __('storefront.home.trust_1_text')],
        ['icon' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6Z" /><path d="m9 12 2 2 4-4" />', 'title' => __('storefront.home.trust_2_title'), 'text' => __('storefront.home.trust_2_text')],
        ['icon' => '<rect x="3" y="6" width="18" height="13" rx="2" /><path d="M3 10h18" />', 'title' => __('storefront.home.trust_3_title'), 'text' => __('storefront.home.trust_3_text')],
        ['icon' => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8" /><path d="M21 3v5h-5" />', 'title' => __('storefront.home.trust_4_title'), 'text' => __('storefront.home.trust_4_text')],
    ];
@endphp
<section class="sf-trust" aria-label="{{ __('storefront.home.trust_label') }}">
    <div class="container">
        <ul class="sf-trust-grid">
            @foreach ($trustTiles as $tile)
                <li class="sf-trust-tile">
                    <span class="sf-trust-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">{!! $tile['icon'] !!}</svg>
                    </span>
                    <span class="sf-trust-body">
                        <b class="sf-trust-title">{{ $tile['title'] }}</b>
                        <span class="sf-trust-text">{{ $tile['text'] }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
