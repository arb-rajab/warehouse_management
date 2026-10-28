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
	<link rel="stylesheet" href="{{ static_asset('assets/css/aiz-seller.css') }}">

    <style>
        body {
            font-size: 12px;
        }
        #map{
            width: 100%;
            height: 250px;
        }
        #edit_map{
            width: 100%;
            height: 250px;
        }
        .pac-container{
            z-index: 100000;
        }

    </style>
	<script>
    	var AIZ = AIZ || {};
        AIZ.local = {
            minimum_quantity: {{ \Illuminate\Support\Js::from(__('storefront.cart.minimum_limit')) }},
            maximum_quantity: {{ \Illuminate\Support\Js::from(__('storefront.cart.maximum_limit')) }},
            nothing_selected: {{ \Illuminate\Support\Js::from(__('storefront.common.nothing_selected')) }},
            nothing_found: {{ \Illuminate\Support\Js::from(__('storefront.common.nothing_found')) }},
            choose_file: {{ \Illuminate\Support\Js::from(__('storefront.upload.choose_file')) }},
            file_selected: {{ \Illuminate\Support\Js::from(__('storefront.upload.file_selected')) }},
            files_selected: {{ \Illuminate\Support\Js::from(__('storefront.upload.files_selected')) }},
            add_more_files: {{ \Illuminate\Support\Js::from(__('storefront.upload.add_more_files')) }},
            adding_more_files: {{ \Illuminate\Support\Js::from(__('storefront.upload.adding_more_files')) }},
            drop_files_here_paste_or: {{ \Illuminate\Support\Js::from(__('storefront.upload.drop_files_here_paste_or')) }},
            browse: {{ \Illuminate\Support\Js::from(__('storefront.upload.browse')) }},
            upload_complete: {{ \Illuminate\Support\Js::from(__('storefront.upload.upload_complete')) }},
            upload_paused: {{ \Illuminate\Support\Js::from(__('storefront.upload.upload_paused')) }},
            resume_upload: {{ \Illuminate\Support\Js::from(__('storefront.upload.resume_upload')) }},
            pause_upload: {{ \Illuminate\Support\Js::from(__('storefront.upload.pause_upload')) }},
            retry_upload: {{ \Illuminate\Support\Js::from(__('storefront.upload.retry_upload')) }},
            cancel_upload: {{ \Illuminate\Support\Js::from(__('storefront.upload.cancel_upload')) }},
            uploading: {{ \Illuminate\Support\Js::from(__('storefront.upload.uploading')) }},
            processing: {{ \Illuminate\Support\Js::from(__('storefront.upload.processing')) }},
            complete: {{ \Illuminate\Support\Js::from(__('storefront.common.complete')) }},
            file: {{ \Illuminate\Support\Js::from(__('storefront.upload.file')) }},
            files: {{ \Illuminate\Support\Js::from(__('storefront.upload.files')) }},
            selected_files: {{ \Illuminate\Support\Js::from(collect([0, 1, 2, 3, 11])->mapWithKeys(fn ($count) => [$count => trans_choice('storefront.upload.selected_files', $count, ['count' => ':count'])])) }},
        }
	</script>

</head>
<body class="">

	<div class="aiz-main-wrapper">
        @include('shop.seller.inc.seller_sidenav')
		<div class="aiz-content-wrapper">
            @include('shop.seller.inc.seller_nav')
			<div class="aiz-main-content">
				<div class="px-15px px-lg-25px">
                    @yield('panel_content')
				</div>
				<div class="bg-white text-center py-3 px-15px px-lg-25px mt-auto border-sm-top">
					<p class="mb-0">&copy; {{ get_setting('site_name') }} v{{ get_setting('current_version') }}</p>
				</div>
			</div><!-- .aiz-main-content -->
		</div><!-- .aiz-content-wrapper -->
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


        if ($('#lang-change').length > 0) {
            $('#lang-change .dropdown-menu a').each(function() {
                $(this).on('click', function(e){
                    e.preventDefault();
                    var $this = $(this);
                    var locale = $this.data('flag');
                    $.post('{{ route('language.change') }}',{_token:'{{ csrf_token() }}', locale:locale}, function(data){
                        location.reload();
                    });

                });
            });
        }
        function menuSearch(){
			var filter, item;
			filter = $("#menu-search").val().toUpperCase();
			items = $("#main-menu").find("a");
			items = items.filter(function(i,item){
				if($(item).find(".aiz-side-nav-text")[0].innerText.toUpperCase().indexOf(filter) > -1 && $(item).attr('href') !== '#'){
					return item;
				}
			});

			if(filter !== ''){
				$("#main-menu").addClass('d-none');
				$("#search-menu").html('')
				if(items.length > 0){
					for (i = 0; i < items.length; i++) {
						const text = $(items[i]).find(".aiz-side-nav-text")[0].innerText;
						const link = $(items[i]).attr('href');
						 $("#search-menu").append(`<li class="aiz-side-nav-item"><a href="${link}" class="aiz-side-nav-link"><i class="las la-ellipsis-h aiz-side-nav-icon"></i><span>${text}</span></a></li`);
					}
				}else{
					$("#search-menu").empty().append($('<li class="aiz-side-nav-item"></li>').append($('<span class="text-center text-muted d-block"></span>').text({{ \Illuminate\Support\Js::from(__('storefront.common.nothing_found')) }})));
				}
			}else{
				$("#main-menu").removeClass('d-none');
				$("#search-menu").html('')
			}
        }
    </script>

</body>
</html>
