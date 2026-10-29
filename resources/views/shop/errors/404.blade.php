@extends('shop.frontend.layouts.app')

@section('robots', 'noindex, nofollow')

@section('content')
<section class="text-center py-6">
	<div class="container">
		<div class="row">
			<div class="col-lg-6 mx-auto">
				<img src="{{ static_asset('assets/img/404.svg') }}" class="mw-100 mx-auto mb-5" height="300">
			    <h1 class="fw-700">{{ __('storefront.common.page_not_found') }}</h1>
			    <p class="fs-16 opacity-60">{{ __('storefront.common.the_page_you_are_looking_for_has_not_been_found_on_our_server') }}</p>
			</div>
		</div>
    </div>
</section>
@endsection
