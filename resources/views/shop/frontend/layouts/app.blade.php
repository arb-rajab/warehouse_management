<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', \App\Support\StoreLocale::uiLocale()) }}" dir="{{ \App\Support\StoreLocale::isRtl() ? 'rtl' : 'ltr' }}">
<head>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ url('/temp') }}">
    <meta name="file-base-url" content="{{ my_asset('') }}">

    {{-- Title, description, canonical, robots and the Open Graph / Twitter tags
         come from App\Support\Seo: a view sets them with the meta_title,
         meta_description, meta_keywords, meta_image, og_type, robots and
         canonical sections, and the settings fill in whatever it leaves blank.
         @yield('meta') below stays for a view's own extra tags and
         @yield('structured_data') for its JSON-LD. --}}
    @php
        $seo = app(\App\Support\Seo::class)->head([
            'title' => $__env->yieldContent('meta_title'),
            'description' => $__env->yieldContent('meta_description'),
            'keywords' => $__env->yieldContent('meta_keywords'),
            'image' => $__env->yieldContent('meta_image'),
            'type' => $__env->yieldContent('og_type'),
            'robots' => $__env->yieldContent('robots'),
            'canonical' => $__env->yieldContent('canonical'),
        ]);
    @endphp
    <title>{{ $seo['title'] }}</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shop.frontend.partials.seo_meta')

    @yield('meta')

    @yield('structured_data')

    <!-- Favicon -->
    <link rel="icon" href="{{ uploaded_asset(get_setting('site_icon')) }}">

    <!-- CSS Files -->
    <link rel="stylesheet" href="{{ static_asset('assets/css/vendors.css') }}">
    @if(\App\Support\StoreLocale::isRtl())
    <link rel="stylesheet" href="{{ static_asset('assets/css/bootstrap-rtl.min.css') }}">
    @endif
    <link rel="stylesheet" href="{{ static_asset('assets/css/aiz-core.css') }}">
    <link rel="stylesheet" href="{{ static_asset('assets/css/custom-style.css') }}">
    {{-- Storefront identity (Direction A): tokens, self-hosted IBM Plex fonts
         and the theme overrides. It loads after the theme so it wins; the
         base_color setting no longer drives the colour. --}}
    @vite('resources/css/storefront.css')
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-latin-400-normal.woff2') }}">
    @if(\App\Support\StoreLocale::isRtl())
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-400-normal.woff2') }}">
    @endif


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

{{-- The IDs are the effective ones (saved in the admin, else .env: App\Support\StoreIntegrationIds); a block whose switch is on but whose ID is empty prints nothing. --}}
@php
    $trackingId = \App\Support\StoreIntegrationIds::get('google_analytics_tracking_id');
@endphp
@if (get_setting('google_analytics') == 1 && $trackingId !== null)
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($trackingId) }}"></script>

    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', {{ \Illuminate\Support\Js::from($trackingId) }});
    </script>
@endif

@php
    $pixelId = \App\Support\StoreIntegrationIds::get('facebook_pixel_id');
@endphp
@if (get_setting('facebook_pixel') == 1 && $pixelId !== null)
    <!-- Facebook Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', {{ \Illuminate\Support\Js::from($pixelId) }});
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ urlencode($pixelId) }}&ev=PageView&noscript=1"/>
    </noscript>
    <!-- End Facebook Pixel Code -->
@endif

@php
    echo get_setting('header_script');
@endphp

</head>
<body>
    <!-- aiz-main-wrapper -->
    <div class="aiz-main-wrapper d-flex flex-column bg-white">

        <!-- Header -->
        @include('shop.frontend.inc.nav')

        @yield('content')

        @if (get_setting('registration_tutorial_button') && !auth()->check() && request()->route() && !in_array(request()->route()->getName(), ['user.register', 'user.login']))
            <a id="registration-button" class="registration-tutorial" href="{{ get_setting('registration_tutorial_link') }}" target="_blank">
                <i class="lab la-info-circle my-float"></i>
                {!! get_setting('registration_tutorial_text') ?? __('storefront.auth.don_t_know_how_to_register') . "?<br>" . __('storefront.common.click_here') . "!" !!}
            </a>
        @endif

        @auth
            @if (auth()->user()->is_rep && !auth()->user()->has_open_visit())
                <a  class="visit-button" href="{{ route('checkout.getCustomers') }}">
                    <i class="las la-plus"></i>
                    <span>{{ __('storefront.common.new_visit') }}</span>
                </a>
            @elseif(auth()->user()->is_rep && auth()->user()->has_open_visit())
                <a class="visit-button" href="{{ route('checkout.getCustomers') }}">
                    <span>{{ __('storefront.common.current_visit_is_to') .' '. auth()->user()->rep_last_open_visit()->customer->name }}</span>
                </a>
            @endif
        @endauth

        <a  class="whats-app" href="https://wa.me/+31684468647" target="_blank">
            <i class="lab la-whatsapp my-float"></i>

        </a>
        @include('shop.frontend.inc.footer')

    </div>

    <!-- cookies agreement -->
    @if (get_setting('show_cookies_agreement') == 'on')
        <div class="aiz-cookie-alert shadow-xl">
            <div class="p-3 bg-dark rounded">
                <div class="text-white mb-3">
                    @php
                        echo get_setting('cookies_agreement_text');
                    @endphp
                </div>
                <button class="btn btn-primary aiz-cookie-accept">
                    {{ __('storefront.common.ok_i_understood') }}
                </button>
            </div>
        </div>
    @endif

    <!-- website popup -->
    @if (get_setting('show_website_popup') == 'on')
        <div class="modal website-popup removable-session d-none" data-key="website-popup" data-value="removed">
            <div class="absolute-full bg-black opacity-60"></div>
            <div class="modal-dialog modal-dialog-centered modal-dialog-zoom modal-md mx-4 mx-md-auto">
                <div class="modal-content position-relative border-0 rounded-0">
                    <div class="aiz-editor-data">
                        {!! clean_html(get_setting('website_popup_content')) !!}
                    </div>
                    @if (get_setting('show_subscribe_form') == 'on')
                        <div class="pb-5 pt-4 px-3 px-md-5">
                            <form class="" method="POST" action="{{ route('subscribers.store') }}">
                                @csrf
                                <div class="form-group mb-0">
                                    <input type="email" class="form-control" placeholder="{{ __('storefront.auth.your_email_address') }}" name="email" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block mt-3">
                                    {{ __('storefront.common.subscribe_now') }}
                                </button>
                            </form>
                        </div>
                    @endif
                    <button class="absolute-top-right bg-white shadow-lg btn btn-circle btn-icon mr-n3 mt-n3 set-session" data-key="website-popup" data-value="removed" data-toggle="remove-parent" data-parent=".website-popup">
                        <i class="la la-close fs-20"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('shop.frontend.partials.modal')

    @include('shop.frontend.partials.account_delete_modal')

    <div class="modal fade" id="addToCart">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-zoom product-modal" id="modal-size" role="document">
            <div class="modal-content position-relative">
                <div class="c-preloader text-center p-3">
                    <i class="las la-spinner la-spin la-3x"></i>
                </div>
                <button type="button" class="close absolute-top-right btn-icon close z-1 btn-circle sf-modal-close mr-2 mt-2 d-flex justify-content-center align-items-center" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                    <span aria-hidden="true" class="fs-24 fw-700" style="margin-left: 2px;">&times;</span>
                </button>
                <div id="addToCart-modal-body">

                </div>
            </div>
        </div>
    </div>

    @yield('modal')

    <!-- SCRIPTS -->
    <script src="{{ static_asset('assets/js/vendors.js') }}"></script>
    <script src="{{ static_asset('assets/js/aiz-core.js') }}"></script>



    @php
        $facebookPageId = \App\Support\StoreIntegrationIds::get('facebook_page_id');
    @endphp
    @if (get_setting('facebook_chat') == 1 && $facebookPageId !== null)
        <script type="text/javascript">
            window.fbAsyncInit = function() {
                FB.init({
                  xfbml            : true,
                  version          : 'v3.3'
                });
              };

              (function(d, s, id) {
              var js, fjs = d.getElementsByTagName(s)[0];
              if (d.getElementById(id)) return;
              js = d.createElement(s); js.id = id;
              js.src = 'https://connect.facebook.net/en_US/sdk/xfbml.customerchat.js';
              fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));
        </script>
        <div id="fb-root"></div>
        <!-- Your customer chat code -->
        <div class="fb-customerchat"
          attribution=setup_tool
          page_id="{{ $facebookPageId }}">
        </div>
    @endif

    <script>
        @foreach (collect(session('flash_notification', []))->toArray() as $message)
            AIZ.plugins.notify({{ \Illuminate\Support\Js::from($message['level']) }}, {{ \Illuminate\Support\Js::from($message['message']) }});
        @endforeach
    </script>

    <script>
        $(document).ready(function() {
            $('.category-nav-element').each(function(i, el) {
                $(el).on('mouseover', function(){
                    if(!$(el).find('.sub-cat-menu').hasClass('loaded')){
                        $.post('{{ route('category.elements') }}', {_token: AIZ.data.csrf, id:$(el).data('id')}, function(data){
                            $(el).find('.sub-cat-menu').addClass('loaded').html(data);
                        });
                    }
                });
            });

            if ($('#currency-change').length > 0) {
                $('#currency-change .dropdown-menu a').each(function() {
                    $(this).on('click', function(e){
                        e.preventDefault();
                        var $this = $(this);
                        var currency_code = $this.data('currency');
                        $.post('{{ route('currency.change') }}',{_token: AIZ.data.csrf, currency_code:currency_code}, function(data){
                            location.reload();
                        });

                    });
                });
            }
        });

        $('#search').on('keyup', function(){
            search();
        });

        $('#search').on('focus', function(){
            search();
        });

        function search(){
            var searchKey = $('#search').val();
            if(searchKey.length > 0){
                $('body').addClass("typed-search-box-shown");

                $('.typed-search-box').removeClass('d-none');
                $('.search-preloader').removeClass('d-none');
                $.post('{{ route('search.ajax') }}', { _token: AIZ.data.csrf, search:searchKey}, function(data){
                    if(data == '0'){
                        // $('.typed-search-box').addClass('d-none');
                        $('#search-content').html(null);
                        $('.typed-search-box .search-nothing').removeClass('d-none').html(({{ \Illuminate\Support\Js::from(__('storefront.common.sorry_nothing_found_for')) }} + ' <strong>"')+searchKey+'"</strong>');
                        $('.search-preloader').addClass('d-none');

                    }
                    else{
                        $('.typed-search-box .search-nothing').addClass('d-none').html(null);
                        $('#search-content').html(data);
                        $('.search-preloader').addClass('d-none');
                    }
                });
            }
            else {
                $('.typed-search-box').addClass('d-none');
                $('body').removeClass("typed-search-box-shown");
            }
        }

        $(".aiz-user-top-menu").on("mouseover", function (event) {
            $(".hover-user-top-menu").addClass('active');
        })
        .on("mouseout", function (event) {
            $(".hover-user-top-menu").removeClass('active');
        });

        $(document).on("click", function(event){
            var $trigger = $("#category-menu-bar");
            if($trigger !== event.target && !$trigger.has(event.target).length){
                $("#click-category-menu").slideUp("fast");;
                $("#category-menu-bar-icon").removeClass('show');
            }
        });

        function updateNavCart(view,count){
            $('.cart-count').html(count);
            $('#cart_items').html(view);
        }

        function removeFromCart(key){
            $.post('{{ route('cart.removeFromCart') }}', {
                _token  : AIZ.data.csrf,
                id      :  key
            }, function(data){
                updateNavCart(data.nav_cart_view,data.cart_count);
                $('#cart-summary').html(data.cart_view);
                AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('storefront.cart.item_has_been_removed_from_cart')) }});
                $('#cart_items_sidenav').html(parseInt($('#cart_items_sidenav').html())-1);
                window.location.reload();
            });
        }

        function addToCompare(id){
            $.post('{{ route('compare.addToCompare') }}', {_token: AIZ.data.csrf, id:id}, function(data){
                $('#compare').html(data);
                AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('storefront.cart.item_has_been_added_to_compare_list')) }});
                $('#compare_items_sidenav').html(parseInt($('#compare_items_sidenav').html())+1);
            });
        }

        function addToWishList(id){
            @if (Auth::check() && Auth::user()->user_type == 'customer')
                $.post('{{ route('wishlists.store') }}', {_token: AIZ.data.csrf, id:id}, function(data){
                    if(data != 0){
                        $('#wishlist').html(data);
                        AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('storefront.cart.item_has_been_added_to_wishlist')) }});
                    }
                    else{
                        AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.auth.please_login_first')) }});
                    }
                });
            @elseif(Auth::check() && Auth::user()->user_type != 'customer')
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.auth.please_login_as_a_customer_to_add_products_to_the_wishlist')) }});
            @else
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.auth.please_login_first')) }});
            @endif
        }

        function showAddToCartModal(id){
            if(!$('#modal-size').hasClass('modal-lg')){
                $('#modal-size').addClass('modal-lg');
            }
            $('#addToCart-modal-body').html(null);
            $('#addToCart').modal();
            $('.c-preloader').show();
            $.post('{{ route('cart.showCartModal') }}', {_token: AIZ.data.csrf, id:id}, function(data){
                $('.c-preloader').hide();
                $('#addToCart-modal-body').html(data);
                AIZ.plugins.slickCarousel();
                AIZ.plugins.zoom();
                AIZ.extra.plusMinus();
                getVariantPrice();
            });
        }

        $('#option-choice-form input').on('change', function(){
            getVariantPrice();
        });

        function getVariantPrice(){
            if($('#option-choice-form input[name=quantity]').val() > 0 && checkAddToCartValidity()){
                $.ajax({
                    type:"POST",
                    url: '{{ route('products.variant_price') }}',
                    data: $('#option-choice-form').serializeArray(),
                    success: function(data){
                        $('.product-gallery-thumb .carousel-box').each(function (i) {
                            if($(this).data('variation') && data.variation == $(this).data('variation')){
                                $('.product-gallery-thumb').slick('slickGoTo', i);
                            }
                        })

                        $('#option-choice-form #chosen_price_div').removeClass('d-none');
                        $('#option-choice-form #chosen_price_div #chosen_price').html(data.price);
                        $('#available-quantity').html(data.quantity);
                        var maxAttr = $('.input-number').prop('max')
                        if(data.max_limit < maxAttr){
                            $('.input-number').prop('max', data.max_limit);
                        }
                        if(parseInt(data.in_stock) == 0 && data.digital  == 0){
                           $('.buy-now').addClass('d-none');
                           $('.add-to-cart').addClass('d-none');
                           $('.out-of-stock').removeClass('d-none');
                        }
                        else{
                           $('.buy-now').removeClass('d-none');
                           $('.add-to-cart').removeClass('d-none');
                           $('.out-of-stock').addClass('d-none');
                        }

                        AIZ.extra.plusMinus();
                    }
                });
            }
        }

        function checkAddToCartValidity(){
            var names = {};
            $('#option-choice-form input:radio').each(function() { // find unique names
                names[$(this).attr('name')] = true;
            });
            var count = 0;
            $.each(names, function() { // then count them
                count++;
            });

            if($('#option-choice-form input:radio:checked').length == count){
                return true;
            }

            return false;
        }

        function addToCart(){
            @if(Auth::check() && Auth::user()->user_type != 'customer')
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.auth.please_login_as_a_customer_to_add_products_to_the_cart')) }});
                return false;
            @endif

            if(checkAddToCartValidity()) {
                $('#addToCart').modal();
                $('.c-preloader').show();
                $.ajax({
                    type:"POST",
                    url: '{{ route('cart.addToCart') }}',
                    data: $('#option-choice-form').serializeArray(),
                    success: function(data){
                       $('#addToCart-modal-body').html(null);
                       $('.c-preloader').hide();
                       $('#modal-size').removeClass('modal-lg');
                       $('#addToCart-modal-body').html(data.modal_view);
                       AIZ.extra.plusMinus();
                       AIZ.plugins.slickCarousel();
                       updateNavCart(data.nav_cart_view,data.cart_count);
                    },
                    error: function (data) {
                        $('#addToCart').modal('hide');
                        $('.c-preloader').hide();
                        AIZ.plugins.notify('warning', data.responseJSON.error);
                    }
                });
            }
            else{
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.common.please_choose_all_the_options')) }});
            }
        }

        function buyNow(){
            @if(Auth::check() && Auth::user()->user_type != 'customer')
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.auth.please_login_as_a_customer_to_add_products_to_the_cart')) }});
                return false;
            @endif

            if(checkAddToCartValidity()) {
                $('#addToCart-modal-body').html(null);
                $('#addToCart').modal();
                $('.c-preloader').show();
                $.ajax({
                    type:"POST",
                    url: '{{ route('cart.addToCart') }}',
                    data: $('#option-choice-form').serializeArray(),
                    success: function(data){
                        if(data.status == 1){
                            $('#addToCart-modal-body').html(data.modal_view);
                            updateNavCart(data.nav_cart_view,data.cart_count);
                            window.location.replace("{{ route('cart') }}");
                        }
                        else{
                            $('#addToCart-modal-body').html(null);
                            $('.c-preloader').hide();
                            $('#modal-size').removeClass('modal-lg');
                            $('#addToCart-modal-body').html(data.modal_view);
                        }
                    },error: function (data) {
                        $('#addToCart').modal('hide');
                        $('.c-preloader').hide();
                        AIZ.plugins.notify('warning', data.responseJSON.error);
                    }
               });
            }
            else{
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.common.please_choose_all_the_options')) }});
            }
        }

        function bid_single_modal(bid_product_id, min_bid_amount){
            @if (Auth::check() && (isCustomer() || isSeller()))
                var min_bid_amount_text = ("(" + {{ \Illuminate\Support\Js::from(__('storefront.product.min_bid_amount')) }})+min_bid_amount+")";
                $('#min_bid_amount').text(min_bid_amount_text);
                $('#bid_product_id').val(bid_product_id);
                $('#bid_amount').attr('min', min_bid_amount);
                $('#bid_for_product').modal('show');
            @elseif (Auth::check() && isAdmin())
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.product.sorry_only_customers_sellers_can_bid')) }});
            @else
                $('#login_modal').modal('show');
            @endif
        }

        function clickToSlide(btn,id){
            $('#'+id+' .aiz-carousel').find('.'+btn).trigger('click');
            $('#'+id+' .slide-arrow').removeClass('link-disable');
            var arrow = btn=='slick-prev' ? 'arrow-prev' : 'arrow-next';
            if ($('#'+id+' .aiz-carousel').find('.'+btn).hasClass('slick-disabled')) {
                $('#'+id).find('.'+arrow).addClass('link-disable');
            }
        }

        function goToView(params) {
            document.getElementById(params).scrollIntoView({behavior: "smooth", block: "center"});
        }

        function copyCouponCode(code){
            navigator.clipboard.writeText(code);
            AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('storefront.cart.coupon_code_copied')) }});
        }

        $(document).ready(function(){
            $('.cart-animate').animate({margin : 0}, "slow");

            $({deg: 0}).animate({deg: 360}, {
                duration: 2000,
                step: function(now) {
                    $('.cart-rotate').css({
                        transform: 'rotate(' + now + 'deg)'
                    });
                }
            });

            setTimeout(function(){
                $('.cart-ok').css({ fill: 'var(--primary)' });
            }, 2000);

        });
    </script>

    <script type="text/javascript">
        // Country Code
        var isPhoneShown = true,
            countryData = window.intlTelInputGlobals.getCountryData(),
            input = document.querySelector("#phone-code");

        for (var i = 0; i < countryData.length; i++) {
            var country = countryData[i];
            if (country.iso2 == 'bd') {
                country.dialCode = '88';
            }
        }

        if (input) {
        var iti = intlTelInput(input, {
            separateDialCode: true,
            utilsScript: "{{ static_asset('assets/js/intlTelutils.js') }}?1590403638580",
            onlyCountries: @php echo json_encode(\App\Models\Country::where('status', 1)->pluck('code')->toArray()) @endphp,
            customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
                if (selectedCountryData.iso2 == 'bd') {
                    return "01xxxxxxxxx";
                }
                return selectedCountryPlaceholder;
            }
        });

        var country = iti.getSelectedCountryData();
        $('input[name=country_code]').val(country.dialCode);

        input.addEventListener("countrychange", function(e) {
            // var currentMask = e.currentTarget.placeholder;
            var country = iti.getSelectedCountryData();
            $('input[name=country_code]').val(country.dialCode);

        });

        }

        function toggleEmailPhone(el) {
            if (isPhoneShown) {
                $('.phone-form-group').addClass('d-none');
                $('.email-form-group').removeClass('d-none');
                $('input[name=phone]').val(null);
                isPhoneShown = false;
                $(el).html(('*' + {{ \Illuminate\Support\Js::from(__('storefront.auth.use_phone_instead')) }}));
            } else {
                $('.phone-form-group').removeClass('d-none');
                $('.email-form-group').addClass('d-none');
                $('input[name=email]').val(null);
                isPhoneShown = true;
                $(el).html(('<i>*' + {{ \Illuminate\Support\Js::from(__('storefront.auth.use_email_instead')) }} + '</i>'));
            }
        }
    </script>

    <script>
        var acc = document.getElementsByClassName("aiz-accordion-heading");
        var i;
        for (i = 0; i < acc.length; i++) {
            acc[i].addEventListener("click", function() {
                this.classList.toggle("active");
                var panel = this.nextElementSibling;
                if (panel.style.maxHeight) {
                    panel.style.maxHeight = null;
                } else {
                    panel.style.maxHeight = panel.scrollHeight + "px";
                }
            });
        }
    </script>

    <script>
        $(document).ready(function() {
            $('#registration-button').click(function() {
                $(this).toggleClass('moved'); // Toggle the 'moved' class
            });
        });
    </script>
    @include('shop.partials.image-upload')
    @yield('script')

    @php
        echo get_setting('footer_script');
    @endphp

</body>
</html>
