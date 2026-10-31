<!doctype html>
<html lang="{{ str_replace('_', '-', \App\Support\StoreLocale::uiLocale()) }}" dir="{{ \App\Support\StoreLocale::isRtl() ? 'rtl' : 'ltr' }}">
<head>
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<meta name="app-url" content="{{ url('/temp') }}">
	<meta name="file-base-url" content="{{ my_asset('') }}">

	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="robots" content="noindex, nofollow">

	<!-- Favicon -->
	<link rel="icon" href="{{ uploaded_asset(get_setting('site_icon')) }}">
	<title>{{ get_setting('website_name').' | '.get_setting('site_motto') }}</title>

	<!-- google font -->
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700">

	<!-- aiz core css -->
	<link rel="stylesheet" href="{{ static_asset('assets/css/vendors.css') }}">
    @if(\App\Support\StoreLocale::isRtl())
    <link rel="stylesheet" href="{{ static_asset('assets/css/bootstrap-rtl.min.css') }}">
    @endif
	<link rel="stylesheet" href="{{ static_asset('assets/css/aiz-core.css') }}">

    <style>
        body {
            font-size: 12px;
        }
    </style>
	<script>
    	var AIZ = AIZ || {};
        AIZ.local = {
            minimum_quantity: {{ \Illuminate\Support\Js::from(__('storefront.cart.minimum_limit')) }},
            maximum_quantity: {{ \Illuminate\Support\Js::from(__('storefront.cart.maximum_limit')) }},
            nothing_selected: ({{ \Illuminate\Support\Js::from(__('storefront.common.nothing_selected')) }}),
            nothing_found: ({{ \Illuminate\Support\Js::from(__('storefront.common.nothing_found')) }}),
            choose_file: ({{ \Illuminate\Support\Js::from(__('storefront.upload.choose_file')) }}),
            file_selected: ({{ \Illuminate\Support\Js::from(__('storefront.upload.file_selected')) }}),
            files_selected: ({{ \Illuminate\Support\Js::from(__('storefront.upload.files_selected')) }}),
            add_more_files: ({{ \Illuminate\Support\Js::from(__('storefront.upload.add_more_files')) }}),
            adding_more_files: ({{ \Illuminate\Support\Js::from(__('storefront.upload.adding_more_files')) }}),
            drop_files_here_paste_or: ({{ \Illuminate\Support\Js::from(__('storefront.upload.drop_files_here_paste_or')) }}),
            browse: ({{ \Illuminate\Support\Js::from(__('storefront.upload.browse')) }}),
            upload_complete: ({{ \Illuminate\Support\Js::from(__('storefront.upload.upload_complete')) }}),
            upload_paused: ({{ \Illuminate\Support\Js::from(__('storefront.upload.upload_paused')) }}),
            resume_upload: ({{ \Illuminate\Support\Js::from(__('storefront.upload.resume_upload')) }}),
            pause_upload: ({{ \Illuminate\Support\Js::from(__('storefront.upload.pause_upload')) }}),
            retry_upload: ({{ \Illuminate\Support\Js::from(__('storefront.upload.retry_upload')) }}),
            cancel_upload: ({{ \Illuminate\Support\Js::from(__('storefront.upload.cancel_upload')) }}),
            uploading: ({{ \Illuminate\Support\Js::from(__('storefront.upload.uploading')) }}),
            processing: ({{ \Illuminate\Support\Js::from(__('storefront.upload.processing')) }}),
            complete: ({{ \Illuminate\Support\Js::from(__('storefront.common.complete')) }}),
            file: ({{ \Illuminate\Support\Js::from(__('storefront.upload.file')) }}),
            files: ({{ \Illuminate\Support\Js::from(__('storefront.upload.files')) }}),
        }
	</script>

</head>
<body class="">

	<div class="aiz-main-wrapper d-flex">
        <div class="flex-grow-1">
            @yield('content')
        </div>
    </div><!-- .aiz-main-wrapper -->

    @yield('modal')


    <script src="{{ static_asset('assets/js/vendors.js') }}" ></script>
    <script src="{{ static_asset('assets/js/aiz-core.js') }}" ></script>

    @include('shop.partials.image-upload')
    @yield('script')

    <script type="text/javascript">
        @foreach (collect(session('flash_notification', []))->toArray() as $message)
            AIZ.plugins.notify({{ \Illuminate\Support\Js::from($message['level']) }}, {{ \Illuminate\Support\Js::from($message['message']) }});
        @endforeach
    </script>

</body>
</html>
