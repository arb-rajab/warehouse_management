<script>
    function confirm_modal(delete_url)
    {
        jQuery('#confirm-delete').modal('show', {backdrop: 'static'});
        document.getElementById('delete_link').setAttribute('href' , delete_url);
    }
</script>

<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                {{-- <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button> --}}
                <h4 class="modal-title" id="myModalLabel">{{ __('storefront.common.confirmation')}}</h4>
            </div>

            <div class="modal-body">
                <p>{{ __('storefront.support.delete_confirmation_message')}}</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-0" data-dismiss="modal">{{ __('storefront.common.cancel')}}</button>
                <a id="delete_link" class="btn btn-danger btn-ok rounded-0">{{ __('storefront.common.delete')}}</a>
            </div>
        </div>
    </div>
</div>

<!-- Login Modal -->
@include('shop.frontend.partials.login_modal')

<!-- Bid Modal -->
<div class="modal fade" id="bid_for_product" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ __('storefront.product.bid_for_product') }} <small id="min_bid_amount"></small> </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                </button>
            </div>
            <div class="modal-body">
                {{-- auction_product_bids.store was never wired up as a named
                     web route in either app (see app/Http/Controllers/Api/V2/
                     AuctionProductBidController -- the only backing route is
                     the JSON API's auction/place-bid, not this web form), so
                     route() here always threw and 500'd this always-included
                     modal on every single page. Route::has() keeps that same
                     "the form has never actually worked" reality without
                     taking down the whole page over it. --}}
                <form class="form-horizontal" action="{{ Route::has('auction_product_bids.store') ? route('auction_product_bids.store') : '#' }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="product_id" id="bid_product_id" value="">
                    <div class="form-group">
                        <label class="form-label">
                            {{__('storefront.product.place_bid_price')}}
                            <span class="text-danger">*</span>
                        </label>
                        <div class="form-group">
                            <input type="number" step="0.01" class="form-control form-control-sm" name="amount" id="bid_amount" min="" placeholder="{{ __('storefront.common.enter_amount') }}" required>
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
