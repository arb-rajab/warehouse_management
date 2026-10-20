{{-- The head tags App\Support\Seo computes; $seo is its head() array. --}}
<meta name="robots" content="{{ $seo['robots'] }}">
<meta name="description" content="{{ $seo['description'] }}" />
<meta name="keywords" content="{{ $seo['keywords'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">

<!-- Schema.org markup for Google+ -->
<meta itemprop="name" content="{{ $seo['ogTitle'] }}">
<meta itemprop="description" content="{{ $seo['ogDescription'] }}">
@if ($seo['image'])
    <meta itemprop="image" content="{{ $seo['image'] }}">
@endif

<!-- Twitter Card data -->
<meta name="twitter:card" content="{{ $seo['twitterCard'] }}">
<meta name="twitter:title" content="{{ $seo['ogTitle'] }}">
<meta name="twitter:description" content="{{ $seo['ogDescription'] }}">
@if ($seo['image'])
    <meta name="twitter:image" content="{{ $seo['image'] }}">
@endif

<!-- Open Graph data -->
<meta property="og:title" content="{{ $seo['ogTitle'] }}" />
<meta property="og:type" content="{{ $seo['ogType'] }}" />
<meta property="og:url" content="{{ $seo['canonical'] }}" />
@if ($seo['image'])
    <meta property="og:image" content="{{ $seo['image'] }}" />
@endif
<meta property="og:description" content="{{ $seo['ogDescription'] }}" />
<meta property="og:site_name" content="{{ $seo['siteName'] }}" />
@if ($seo['fbAppId'] !== '')
    <meta property="fb:app_id" content="{{ $seo['fbAppId'] }}">
@endif
