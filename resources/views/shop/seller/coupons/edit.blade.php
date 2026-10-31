@extends('shop.seller.layouts.app')
@section('panel_content')

    <div class="aiz-titlebar mt-2 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3">{{ __('seller.coupons.edit_your_coupon') }}</h1>
            </div>
        </div>
    </div>

    <div class="row gutters-5">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0 h6">{{__('seller.coupons.coupon_information_update')}}</h3>
                </div>
                <form action="{{ route('seller.coupon.update', $coupon->id) }}" method="POST">
                    <input name="_method" type="hidden" value="PATCH">
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
                    <div class="card-body">
                        <input type="hidden" value="{{ $coupon->id }}" id="id">
                        <div class="form-group row">
                            <label class="col-lg-3 col-from-label" for="coupon_type">{{__('seller.coupons.coupon_type')}}<x-required-mark /></label>
                            <div class="col-lg-9">
                                <select name="type" id="coupon_type" class="form-control aiz-selectpicker" onchange="coupon_form()" required>
                                    @if ($coupon->type == "product_base")
                                        <option value="product_base" selected>{{__('seller.coupons.for_products')}}</option>
                                    @elseif ($coupon->type == "cart_base")
                                        <option value="cart_base">{{__('seller.coupons.for_total_orders')}}</option>
                                    @endif
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
                    </div>
                </form>
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
        var id = $('#id').val();
		$.post('{{ route('seller.coupon.get_coupon_form_edit') }}',{_token:'{{ csrf_token() }}', coupon_type:coupon_type, id:id}, function(data){
            $('#coupon_form').html(data);
            AIZ.plugins.bootstrapSelect('refresh');
            coupon_discount_limit();
            $('.aiz-date-range').daterangepicker();

		});
    }




</script>

@endsection
