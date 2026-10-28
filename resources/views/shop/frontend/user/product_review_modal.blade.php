<div class="modal-header">
    <h5 class="modal-title h6">{{__('storefront.product.review')}}</h5>
    <button type="button" class="close" data-dismiss="modal">
    </button>
</div>

@if($review == null)
    <!-- Add new review -->
    <form action="{{ route('reviews.store') }}" method="POST" >
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <div class="modal-body">
            <div class="form-group">
                <label class="opacity-60">{{ __('storefront.product.product')}}</label>
                <p>{{ $product->getTranslation('name') }}</p>
            </div>
            <!-- Rating -->
            <div class="form-group">
                <label class="opacity-60">{{ __('storefront.product.rating')}}</label>
                <div class="rating rating-input">
                    <label>
                        <input type="radio" name="rating" value="1" required>
                        <i class="las la-star"></i>
                    </label>
                    <label>
                        <input type="radio" name="rating" value="2">
                        <i class="las la-star"></i>
                    </label>
                    <label>
                        <input type="radio" name="rating" value="3">
                        <i class="las la-star"></i>
                    </label>
                    <label>
                        <input type="radio" name="rating" value="4">
                        <i class="las la-star"></i>
                    </label>
                    <label>
                        <input type="radio" name="rating" value="5">
                        <i class="las la-star"></i>
                    </label>
                </div>
            </div>
            <!-- Comment -->
            <div class="form-group">
                <label class="opacity-60">{{ __('storefront.common.comment')}}</label>
                <textarea class="form-control rounded-0" rows="4" name="comment" placeholder="{{ __('storefront.product.your_review')}}" required></textarea>
            </div>
            <!-- Review Images -->
            <div class="form-group">
                <label class="" for="photos">{{__('storefront.product.review_images')}}</label>
                <div class="">
                    <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                        <div class="input-group-prepend">
                            <div class="input-group-text bg-soft-secondary font-weight-medium rounded-0">{{ __('storefront.upload.browse')}}</div>
                        </div>
                        <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                        <input type="hidden" name="photos[]" class="selected-files">
                    </div>
                    <div class="file-preview box sm">
                    </div>
                    <small class="text-muted">{{__('storefront.product.these_images_are_visible_in_product_review_page_gallery_upload_square_images')}}</small>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary rounded-0" data-dismiss="modal">{{__('storefront.common.cancel')}}</button>
            <button type="submit" class="btn btn-sm btn-primary rounded-0">{{__('storefront.product.submit_review')}}</button>
        </div>
    </form>
@else
    <!-- Review -->
    <li class="media list-group-item d-flex">
        <div class="media-body text-left">
            <!-- Rating -->
            <div class="form-group">
                <label class="opacity-60">{{ __('storefront.product.rating')}}</label>
                <p class="rating rating-sm">
                    @for ($i=0; $i < $review->rating; $i++)
                        <i class="las la-star active"></i>
                    @endfor
                    @for ($i=0; $i < 5-$review->rating; $i++)
                        <i class="las la-star"></i>
                    @endfor
                </p>
            </div>
            <!-- Comment -->
            <div class="form-group">
                <label class="opacity-60">{{ __('storefront.common.comment')}}</label>
                <p class="comment-text">
                    {{ $review->comment }}
                </p>
            </div>
            <!-- Review Images -->
            <div class="form-group">
                <label class="opacity-60">{{ __('storefront.common.images')}}</label>
                <div class="d-flex flex-wrap">
                    @foreach (explode(',', $review->photos) as $photo)
                        <div class="mr-3 mb-3 size-90px">
                            <img class="img-fit h-100 lazyload border"
                                src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                data-src="{{ uploaded_asset($photo) }}"
                                onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </li>
@endif

