@extends('shop.frontend.layouts.user_panel')

@section('panel_content')
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <b class="fs-20 fw-700 text-dark">{{ __('storefront.cart.wishlist')}}</b>
            </div>
        </div>
    </div>

    @if (count($wishlists) > 0)
        <div class="row row-cols-xxl-5 row-cols-xl-4 row-cols-lg-4 row-cols-md-3 row-cols-sm-2 row-cols-2 gutters-16 border-top border-left mx-1 mx-md-0 mb-4">
            @foreach($wishlists as $key => $wishlist)
                @if ($wishlist->product != null)
                    <div class="aiz-card-box col py-3 text-center border-right border-bottom has-transition hov-shadow-out z-1" id="wishlist_{{ $wishlist->id }}">
                        <div class="position-relative h-140px h-md-200px img-fit overflow-hidden mb-3">
                            <!-- Image -->
                            <a href="{{ \App\Support\StorefrontUrl::to($wishlist->product) }}" class="d-block h-100">
                                <img src="{{ uploaded_asset($wishlist->product->thumbnail_img) }}" class="lazyload mx-auto img-fit"
                                    title="{{ $wishlist->product->getTranslation('name') }}">
                            </a>
                            <!-- Remove from wishlisht -->
                            <div class="absolute-top-right aiz-p-hov-icon">
                                <a href="javascript:void(0)" onclick="removeFromWishlist({{ $wishlist->id }})" data-toggle="tooltip" data-title="{{ __('storefront.cart.remove_from_wishlist') }}" data-placement="left">
                                    <i class="la la-trash"></i>
                                </a>
                            </div>
                            <!-- add to cart -->

                            <a class="cart-btn absolute-bottom-left w-100 h-35px aiz-p-hov-icon text-white fs-13 fw-700 d-flex justify-content-center align-items-center"
                                href="javascript:void(0)" onclick="showAddToCartModal({{ $wishlist->product->id }})">{{ __('storefront.cart.add_to_cart') }}
                            </a>

                        </div>
                        <!-- Product Name -->
                        <h5 class="fs-14 mb-0 lh-1-5 fw-400 text-truncate-2 mb-3">
                            <a href="{{ \App\Support\StorefrontUrl::to($wishlist->product) }}" class="text-reset hov-text-primary"
                                title="{{ $wishlist->product->getTranslation('name') }}">{{ $wishlist->product->getTranslation('name') }}</a>
                        </h5>
                        <!-- Price -->
                        @if(Auth::check() && Auth::user()->admin_verified)
                        <div class="fs-14">
                            <span class="fw-600 text-primary">{{ home_discounted_base_price($wishlist->product) }}</span>
                            @if(home_base_price($wishlist->product) != home_discounted_base_price($wishlist->product))
                                <del class="opacity-60 ml-1">{{ home_base_price($wishlist->product) }}</del>
                            @endif
                        </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <div class="row">
            <div class="col">
                <div class="text-center bg-white p-4 border">
                    <img class="mw-100 h-200px" src="{{ static_asset('assets/img/nothing.svg') }}" alt="Image">
                    <h5 class="mb-0 h5 mt-3">{{ __('storefront.common.there_isn_t_anything_added_yet')}}</h5>
                </div>
            </div>
        </div>
    @endif
    <!-- Pagination -->
    <div class="aiz-pagination">
        {{ $wishlists->links() }}
    </div>
@endsection

@section('modal')
    <!-- add To Cart Modal -->
    <div class="modal fade" id="addToCart" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-zoom product-modal" id="modal-size" role="document">
            <div class="modal-content position-relative">
                <div class="c-preloader">
                    <i class="fa fa-spin fa-spinner"></i>
                </div>
                <button type="button" class="close absolute-close-btn" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
                <div id="addToCart-modal-body">

                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script type="text/javascript">
        function removeFromWishlist(id){
            $.post('{{ route('wishlists.remove') }}',{_token:'{{ csrf_token() }}', id:id}, function(data){
                $('#wishlist').html(data);
                $('#wishlist_'+id).hide();
                AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('storefront.cart.item_has_been_renoved_from_wishlist')) }});
            })
        }
    </script>
@endsection
