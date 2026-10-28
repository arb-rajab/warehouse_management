@extends('shop.seller.layouts.app')
@section('panel_content')

    <div class="aiz-titlebar mt-2 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3">{{ __('seller.coupons.add_your_coupon') }}</h1>
            </div>
        </div>
    </div>

    <div class="row gutters-5">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{__('seller.coupons.coupon_information_adding')}}</h5>
                </div>
                
                <div class="card-body">
                    <form class="form-horizontal" action="{{ route('seller.coupon.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <x-required-legend />
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mt-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="form-group row">
                            <label class="col-lg-3 col-from-label" for="coupon_type">{{__('seller.coupons.coupon_type')}}<x-required-mark /></label>
                            <div class="col-lg-9">
                                <select name="type" id="coupon_type" class="form-control aiz-selectpicker" onchange="coupon_form()" required>
                                    <option value="">{{__('seller.coupons.select_one') }}</option>
                                    <option value="product_base" @if (old('type') == 'product_base') selected @endif>{{__('seller.coupons.for_products')}}</option>
                                    <option value="cart_base" @if (old('type', 'cart_base') == 'cart_base') selected @endif>{{__('seller.coupons.for_total_orders')}}</option>
                                </select>
                                @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div id="coupon_form">
                            @include('shop.seller.coupons.fields')
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-primary">{{__('storefront.common.save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')

<script type="text/javascript">
    function coupon_discount_limit() {
        $('#coupon-discount').attr('max', $('#coupon-discount-type').val() === 'percent' ? '100' : '999999999999999999.99');
    }
    $(function () { $('.aiz-date-range').daterangepicker(); coupon_discount_limit(); });
    function coupon_form(){
        var coupon_type = $('#coupon_type').val();
		$.post('{{ route('seller.coupon.get_coupon_form') }}',{_token:'{{ csrf_token() }}', coupon_type:coupon_type}, function(data){
            $('#coupon_form').html(data);
            AIZ.plugins.bootstrapSelect('refresh');
            coupon_discount_limit();
            $('.aiz-date-range').daterangepicker();
		});
    }



</script>
@endsection
