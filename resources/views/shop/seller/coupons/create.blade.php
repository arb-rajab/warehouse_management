@extends('shop.seller.layouts.app')
@section('panel_content')

    <div class="aiz-titlebar mt-2 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3">{{ translate('Add Your Coupon') }}</h1>
            </div>
        </div>
    </div>

    <div class="row gutters-5">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{translate('Coupon Information Adding')}}</h5>
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
                            <label class="col-lg-3 col-from-label" for="coupon_type">{{translate('Coupon Type')}}<x-required-mark /></label>
                            <div class="col-lg-9">
                                <select name="type" id="coupon_type" class="form-control aiz-selectpicker" onchange="coupon_form()" required>
                                    <option value="">{{translate('Select One') }}</option>
                                    <option value="product_base" @if (old('type') == 'product_base') selected @endif>{{translate('For Products')}}</option>
                                    <option value="cart_base" @if (old('type', 'cart_base') == 'cart_base') selected @endif>{{translate('For Total Orders')}}</option>
                                </select>
                                @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div id="coupon_form">
                            @include('shop.seller.coupons.fields')
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-primary">{{translate('Save')}}</button>
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
