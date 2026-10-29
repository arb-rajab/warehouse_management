@extends('shop.frontend.layouts.app')

@section('meta_title'){{ $blog->meta_title }}@stop

@section('meta_description'){{ $blog->meta_description }}@stop

@section('meta_keywords'){{ $blog->meta_keywords }}@stop

@section('meta_image'){{ ($blog->meta_img ?: $blog->banner) ? uploaded_asset($blog->meta_img ?: $blog->banner) : '' }}@stop

@section('og_type', 'article')

@section('structured_data')
    @include('shop.frontend.partials.json_ld', ['schemas' => [\App\Support\StructuredData::blogPost($blog)]])
@endsection

{{-- An unpublished post is reachable by its link; it is not for the index. --}}
@section('robots'){{ $blog->status == 1 ? 'index, follow' : 'noindex, nofollow' }}@stop

@section('content')

<section class="py-4">
    <div class="container">
        <div class="row gutters-16 justify-content-center">

            <!-- Blog Details -->
            <div class="col-xl-7 col-lg-8">
                <div class="mb-4">
                    <!-- Title -->
                    <h2 class="fs-20 fs-md-24 fw-700 mb-3 h-35px text-truncate">
                        <a href="{{ url("blog").'/'. $blog->slug }}" class="text-reset hov-text-primary" title="{{ $blog->title }}">
                            {{ $blog->title }}
                        </a>
                    </h2>
                    <div class="row">
                        <div class="col-4">
                            <!-- Date -->
                            <div>
                                <small class="fs-12 fw-400 opacity-60">{{ \App\Support\StoreLocale::date(strtotime($blog->created_at)) }}</small>
                            </div>
                            <!-- Caregory -->
                            @if($blog->category != null)
                                <div>
                                    <small class="fs-12 fw-400 text-blue">{{ $blog->category->category_name }}</small>
                                </div>
                            @endif
                        </div>
                        <!-- Share -->
                        <div class="col-8 text-right">
                            <div class="aiz-share"></div>
                        </div>
                    </div>
                    <!-- Image -->
                    <img src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                        data-src="{{ uploaded_asset($blog->banner) }}"
                        alt="{{ $blog->title }}"
                        class="img-fluid lazyload w-100 mt-3 mb-4">
                    <!-- Description -->
                    <div class="mb-4 overflow-hidden">
                        {!! clean_html($blog->description) !!}
                    </div>
                    <!-- Facebook Comment -->
                    @if (get_setting('facebook_comment') == 1)
                    <div class="mb-4">
                        <div class="fb-comments" data-href="{{ route("blog",$blog->slug) }}" data-width="" data-numposts="5"></div>
                    </div>
                    @endif
                </div>
            </div>


            <!-- recent posts -->
            <div class="col-xl-3 col-lg-4">
                <div class="p-3 border">
                    <h3 class="fs-16 fw-700 text-dark mb-3">{{ __('storefront.common.recent_posts') }}</h3>
                    <div class="row">
                        @foreach($recent_blogs as $recent_blog)
                        <div class="col-lg-12 col-sm-6 mb-4 hov-scale-img">
                            <div class="row">
                                <div class="col-4">
                                    <!-- Image -->
                                    <a href="{{ url("blog").'/'. $recent_blog->slug }}" class="text-reset d-block overflow-hidden size-90px mr-2">
                                        <img src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                                            data-src="{{ uploaded_asset($recent_blog->banner) }}"
                                            alt="{{ $recent_blog->title }}"
                                            class="img-fit lazyload h-100 has-transition">
                                    </a>
                                </div>
                                <div class="col-8">
                                    <!-- Title -->
                                    <h2 class="fs-14 fw-700 mb-3 h-35px text-truncate-2">
                                        <a href="{{ url("blog").'/'. $recent_blog->slug }}" class="text-reset hov-text-primary" title="{{ $recent_blog->title }}">
                                            {{ $recent_blog->title }}
                                        </a>
                                    </h2>
                                    <!-- Date -->
                                    <div>
                                        <small class="fs-12 fw-400 opacity-60">{{ \App\Support\StoreLocale::date(strtotime($recent_blog->created_at)) }}</small>
                                    </div>
                                    <!-- Category -->
                                    @if($recent_blog->category != null)
                                        <div>
                                            <small class="fs-12 fw-400 text-blue">{{ $recent_blog->category->category_name }}</small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

@endsection


@section('script')
    @if (get_setting('facebook_comment') == 1)
        <div id="fb-root"></div>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v9.0&appId={{ config('services.facebook.app_id') }}&autoLogAppEvents=1" nonce="ji6tXwgZ"></script>
    @endif
@endsection
