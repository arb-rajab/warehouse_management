@extends('frontend.layouts.app')



@section('content')


@if ($info)

<div class="container my-5  ">
    <h2 class="text-primary" style="text-align: center">Personal Information </h2>
    <div class="row justify-content-center mt-3 ">
    <div class="col-4 my-2">
        <div class="card">
        <h5 class="card-header">{{ $info->name }}</h5>
      <div class="card-body py-4">
        <p><strong>Email:</strong> {{ $info->email }} </p>
        <p><strong>Phone:</strong> {{ $info->phone_number }} </p>
        <p><strong>Country:</strong> {{ $info->country }} </p>
        <p><strong>City:</strong> {{ $info->city }} </p>
      </div>
    </div>
        <div class=" py-4 text-center">
            <a class="btn btn-primary " href="{{ route('create.connection',  ['user_hash' => $info->user_hash]) }}">Let's Share contacts!</a>
        </div>
</div>

@php
$best_selers = Cache::remember('best_selers', 86400, function () {
    return \App\Models\Shop::where('verification_status', 1)->orderBy('num_of_sale', 'desc')->take(5)->get();
});
@endphp
    </div>

</div>

<section class="mb-2 mb-md-4 mt-2 mt-md-3">
    <div class="container">
        <!-- Sellers Section -->
        <div class="aiz-carousel arrow-x-0 arrow-inactive-none" data-items="5" data-xxl-items="5" data-xl-items="4" data-lg-items="3.4" data-md-items="2.5" data-sm-items="2" data-xs-items="1.4" data-arrows="true" data-dots="false">
            @foreach ($best_selers as $key => $seller)
                @if ($seller->user != null)
                    <div class="carousel-box h-100 position-relative text-center border-right border-top border-bottom @if($key==0) border-left @endif has-transition hov-animate-outline">
                        <div class="position-relative px-3" style="padding-top: 2rem; padding-bottom:2rem;">
                            <!-- Shop logo & Verification Status -->
                            <div class="position-relative mx-auto size-100px size-md-120px">
                                <a href="{{ route('shop.visit', $seller->slug) }}" class="d-flex mx-auto justify-content-center align-item-center size-100px size-md-120px border overflow-hidden hov-scale-img" tabindex="0" style="border: 1px solid #e5e5e5; border-radius: 50%; box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.06);">
                                    <img src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                                        data-src="{{ uploaded_asset($seller->logo) }}"
                                        alt="{{ $seller->name }}"
                                        class="img-fit lazyload has-transition"
                                        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder-rect.jpg') }}';">
                                </a>
                            </div>
                            <!-- Shop name -->
                            <h2 class="fs-14 fw-700 text-dark text-truncate-2 h-40px mt-3 mt-md-4 mb-0 mb-md-3">
                                <a href="{{ route('shop.visit', $seller->slug) }}" class="text-reset hov-text-primary" tabindex="0">{{ $seller->name }}</a>
                            </h2>
                            <!-- Visit Button -->
                            <a href="{{ route('shop.visit', $seller->slug) }}" class="btn-visit">
                                <span class="circle" aria-hidden="true">
                                    <span class="icon arrow"></span>
                                </span>
                                <span class="button-text">{{ translate('Visit Store') }}</span>
                            </a>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection
