@extends('shop.frontend.layouts.app')

@section('robots', 'noindex, nofollow')

@section('content')
<section class="text-center py-6">
	<div class="container">
		<div class="row">
			<div class="col-lg-6 mx-auto">
				<img src="{{ static_asset('assets/img/500.svg') }}" class="img-fluid w-75">
				<h1 class="h2 fw-700 mt-5">{{ __('storefront.common.something_went_wrong_label') }}</h1>
			<p class="fs-16 opacity-60">{{ __('storefront.common.sorry_for_the_inconvenience_but_we_re_working_on_it') }} <br> {{ __('storefront.common.error_code') }}: 500</p>
			</div>
		</div>
	</div>
</section>
@endsection
