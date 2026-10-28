@extends('shop.frontend.layouts.app')

@section('meta_title'){{ $detailedProduct->meta_title }}@stop

@section('meta_description'){{ $detailedProduct->meta_description }}@stop

@section('meta_keywords'){{ $detailedProduct->tags }}@stop

@section('meta_image'){{ ($detailedProduct->meta_img ?: $detailedProduct->thumbnail_img) ? uploaded_asset($detailedProduct->meta_img ?: $detailedProduct->thumbnail_img) : '' }}@stop

@section('og_type', 'product')

@section('structured_data')
    @include('shop.frontend.partials.json_ld', ['schemas' => \App\Support\StructuredData::product($detailedProduct)])
@endsection

@section('meta')
    <meta property="product:price:currency"
        content="{{ \App\Models\Currency::findOrFail(get_setting('system_default_currency'))->code }}" />
@endsection

@section('content')
    <section class="mb-4 pt-3">
        <div class="container">
            <div class="bg-white py-3">
                <div class="row">
                    <!-- Product Image Gallery -->
                    <div class="col-xl-4 col-lg-4 mb-4">
                        @include('shop.frontend.product_details.image_gallery')
                    </div>

                    <!-- Product Details -->
                    <div class="col-xl-4 col-lg-4">
                        @include('shop.frontend.product_details.details')
                    </div>

                    <div class="col-xl-4 col-lg-4">
                        @include('shop.frontend.product_details.description')
                        @if ($detailedProduct->barcodes->count() > 0)
                            <div class="bg-white mb-4 border p-3 p-sm-4">
                                <p class="mr-5 pb-2 fs-16 fw-700 text-reset">{{ __('Barcodes') }}</p>
                                    @foreach ($detailedProduct->barcodes as $barcode)
                                        @php
                                            $englishBarcode = ar_barcode_to_en($barcode->barcode);
                                            $barcode->barcode = $englishBarcode;
                                        @endphp
                                        <div class="mb-5">
                                            <img alt='Barcode Generator TEC-IT'
                                                src='https://barcode.tec-it.com/barcode.ashx?data={{ $barcode->barcode }}&code=Code128&translate-esc=on'/>
                                        </div>
                                    @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <div class="container">
            @if ($detailedProduct->auction_product)
                <!-- Reviews & Ratings -->
                @if(get_setting("Product_Reviews_Section") == 1)
                @include('shop.frontend.product_details.review_section')
                @endif

                {{-- <!-- Description, Video, Downloads -->
                @include('shop.frontend.product_details.description') --}}

                <!-- Product Query -->
                @if(get_setting('product_query_activation') == 1)
                @include('shop.frontend.product_details.product_queries')
                @endif
            @else
                <div class="row gutters-16">
                    <!-- Left side -->
                    <div class="col-lg-3">
                        <!-- Seller Info -->
                        @include('shop.frontend.product_details.seller_info')

                        <!-- Top Selling Products -->
                       <div class="d-none d-lg-block">
                            @include('shop.frontend.product_details.top_selling_products')
                       </div>
                    </div>

                    <!-- Right side -->
                    <div class="col-lg-9">

                        <!-- Reviews & Ratings -->
                        @if(get_setting("Product_Reviews_Section") == 1)
                        @include('shop.frontend.product_details.review_section')
                        @endif
                        <!-- Description, Video, Downloads -->
                        {{-- @include('shop.frontend.product_details.description') --}}
                        @php
                            $active_offers = $detailedProduct->offers;
                            if($active_offers->isNotEmpty())
                                $active_offers = $active_offers->toQuery()->active()->productsOffers()->get();
                        @endphp
                        <!-- Offered products -->
                        @if ($active_offers->count() > 0 && (!$active_offers->first()->user_got_offer() || $active_offers->first()->multiple_usage))
                            @include('shop.frontend.product_details.offered_products', ['active_offers' => $active_offers])
                        @endif

                        <!-- Related products -->
                        @include('shop.frontend.product_details.related_products')

                        <!-- Product Query -->
                        @if(get_setting('product_query_activation') == 1)
                        @include('shop.frontend.product_details.product_queries')
                        @endif

                        <!-- Top Selling Products -->
                        <div class="d-lg-none">
                             @include('shop.frontend.product_details.top_selling_products')
                        </div>

                    </div>
                </div>
            @endif
        </div>
    </section>

@endsection

@section('modal')
    <!-- Image Modal -->
    <div class="modal fade" id="image_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom product-modal" id="modal-size" role="document">
            <div class="modal-content position-relative">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="p-4">
                    <div class="size-300px size-lg-450px">
                        <img class="img-fit h-100 lazyload"
                            src="{{ static_asset('assets/img/placeholder.jpg') }}"
                            data-src=""
                            onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Modal -->
    <div class="modal fade" id="chat_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom product-modal" id="modal-size" role="document">
            <div class="modal-content position-relative">
                <div class="modal-header">
                    <h5 class="modal-title fw-600 h5">{{ __('storefront.product.any_query_about_this_product') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form class="" action="{{ route('conversations.store') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    @php $keepInput = $errors->storeConversation->any(); @endphp
                    <input type="hidden" name="product_id" value="{{ $detailedProduct->id }}">
                    <div class="modal-body gry-bg px-3 pt-3">
                        <x-required-legend />
                        @error('product_id', 'storeConversation')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="form-group">
                            <label for="conversation_title">{{ __('storefront.common.title') }}<x-required-mark /></label>
                            <input type="text" id="conversation_title" class="form-control mb-3 rounded-0 @error('title', 'storeConversation') is-invalid @enderror" name="title"
                                value="{{ $keepInput ? old('title') : $detailedProduct->name }}" placeholder="{{ __('storefront.product.product_name') }}"
                                maxlength="1000" required>
                            @error('title', 'storeConversation')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="conversation_message">{{ __('storefront.support.your_question') }}<x-required-mark /></label>
                            <textarea id="conversation_message" class="form-control rounded-0 @error('message', 'storeConversation') is-invalid @enderror" rows="8" name="message" maxlength="65535" required
                                placeholder="{{ __('storefront.support.your_question') }}">{{ $keepInput ? old('message') : \App\Support\StorefrontUrl::to($detailedProduct) }}</textarea>
                            @error('message', 'storeConversation')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary fw-600 rounded-0"
                            data-dismiss="modal">{{ __('storefront.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary fw-600 rounded-0 w-100px">{{ __('storefront.common.send') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bid Modal -->
    @if($detailedProduct->auction_product == 1)
        @php
            $highest_bid = $detailedProduct->bids->max('amount');
            $min_bid_amount = $highest_bid != null ? $highest_bid+1 : $detailedProduct->starting_bid;
        @endphp
        <div class="modal fade" id="bid_for_detail_product" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">{{ __('storefront.product.bid_for_product') }} <small>({{ __('storefront.product.min_bid_amount').$min_bid_amount }})</small> </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                        </button>
                    </div>
                    <div class="modal-body">
                        <form class="form-horizontal" action="{{ route('auction_product_bids.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $detailedProduct->id }}">
                            <div class="form-group">
                                <label class="form-label">
                                    {{__('storefront.product.place_bid_price')}}
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="form-group">
                                    <input type="number" step="0.01" class="form-control form-control-sm" name="amount" min="{{ $min_bid_amount }}" placeholder="{{ __('storefront.common.enter_amount') }}" required>
                                </div>
                            </div>
                            <div class="form-group text-right">
                                <button type="submit" class="btn btn-sm btn-primary transition-3d-hover mr-1">{{ __('storefront.common.submit') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Product Review Modal -->
    <div class="modal fade" id="product-review-modal">
        <div class="modal-dialog">
            <div class="modal-content" id="product-review-modal-content">

            </div>
        </div>
    </div>
@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            getVariantPrice();
        });

        function CopyToClipboard(e) {
            var url = $(e).data('url');
            var $temp = $("<input>");
            $("body").append($temp);
            $temp.val(url).select();
            try {
                document.execCommand("copy");
                AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('storefront.common.link_copied_to_clipboard')) }});
            } catch (err) {
                AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('storefront.common.oops_unable_to_copy')) }});
            }
            $temp.remove();
            // if (document.selection) {
            //     var range = document.body.createTextRange();
            //     range.moveToElementText(document.getElementById(containerid));
            //     range.select().createTextRange();
            //     document.execCommand("Copy");

            // } else if (window.getSelection) {
            //     var range = document.createRange();
            //     document.getElementById(containerid).style.display = "block";
            //     range.selectNode(document.getElementById(containerid));
            //     window.getSelection().addRange(range);
            //     document.execCommand("Copy");
            //     document.getElementById(containerid).style.display = "none";

            // }
            // AIZ.plugins.notify('success', 'Copied');
        }

        function show_chat_modal() {
            @if (Auth::check())
                $('#chat_modal').modal('show');
            @else
                $('#login_modal').modal('show');
            @endif
        }

        @if ($errors->storeConversation->any())
            $(function () { $('#chat_modal').modal('show'); });
        @endif

        // Pagination using ajax
        $(window).on('hashchange', function() {
            if(window.history.pushState) {
                window.history.pushState('', '/', window.location.pathname);
            } else {
                window.location.hash = '';
            }
        });

        $(document).ready(function() {
            $(document).on('click', '.product-queries-pagination .pagination a', function(e) {
                getPaginateData($(this).attr('href').split('page=')[1], 'query', 'queries-area');
                e.preventDefault();
            });
        });

        $(document).ready(function() {
            $(document).on('click', '.product-reviews-pagination .pagination a', function(e) {
                getPaginateData($(this).attr('href').split('page=')[1], 'review', 'reviews-area');
                e.preventDefault();
            });
        });

        function getPaginateData(page, type, section) {
            $.ajax({
                url: '?page=' + page,
                dataType: 'json',
                data: {type: type},
            }).done(function(data) {
                $('.'+section).html(data);
                location.hash = page;
            }).fail(function() {
                alert({{ \Illuminate\Support\Js::from(__('storefront.common.loading_failed')) }});
            });
        }
        // Pagination end

        function showImage(photo) {
            $('#image_modal img').attr('src', photo);
            $('#image_modal img').attr('data-src', photo);
            $('#image_modal').modal('show');
        }

        function bid_modal(){
            @if (Auth::check() && (isCustomer() || isSeller()))
                $('#bid_for_detail_product').modal('show');
          	@elseif (Auth::check() && isAdmin())
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.product.sorry_only_customers_sellers_can_bid')) }});
            @else
                $('#login_modal').modal('show');
            @endif
        }

        function product_review(product_id) {
            @if (Auth::check() && isCustomer())
                @if ($review_status == 1)
                    $.post('{{ route('product_review_modal') }}', {
                        _token: '{{ @csrf_token() }}',
                        product_id: product_id
                    }, function(data) {
                        $('#product-review-modal-content').html(data);
                        $('#product-review-modal').modal('show', {
                            backdrop: 'static'
                        });
                        AIZ.extra.inputRating();
                    });
                @else
                    AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.product.sorry_you_need_to_buy_this_product_to_give_review')) }});
                @endif
            @elseif (Auth::check() && !isCustomer())
                AIZ.plugins.notify('warning', {{ \Illuminate\Support\Js::from(__('storefront.product.sorry_only_customers_can_give_review')) }});
            @else
                $('#login_modal').modal('show');
            @endif
        }
    </script>
@endsection
