@extends('backend.layouts.app')

@section('content')
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h5 class="mb-0 h6">{{ translate('Offer Deal Information') }}</h5>
    </div>

    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-body p-0">
                    <form class="p-4" action="{{ route('offer_deals.update', $offer->id) }}" method="POST">
                        @csrf

                        <div class="form-group row">
                            <label class="col-sm-3 col-from-label" for="name">{{ translate('Title') }} <i
                                    class="las la-language text-danger" title="{{ translate('Translatable') }}"></i></label>
                            <div class="col-sm-9">
                                <input type="text" placeholder="{{ translate('Title') }}" id="name" name="title"
                                    value="{{ $offer->title }}" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label" for="signinSrEmail">{{ translate('Banner') }}
                                <small>(1920x500)</small></label>
                            <div class="col-md-9">
                                <div class="input-group" data-toggle="aizuploader" data-type="image">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text bg-soft-secondary font-weight-medium">
                                            {{ translate('Browse') }}</div>
                                    </div>
                                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                    <input type="hidden" name="banner" value="{{ $offer->banner }}"
                                        class="selected-files">
                                </div>
                                <div class="file-preview box sm">
                                </div>
                            </div>
                        </div>

                        @php
                            $start_date = date('d-m-Y H:i:s', $offer->start_date);
                            $end_date = date('d-m-Y H:i:s', $offer->end_date);
                        @endphp

                        <div class="form-group row">
                            <label class="col-sm-3 col-from-label" for="start_date">{{ translate('Date') }}</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control aiz-date-range"
                                    value="{{ $start_date . ' to ' . $end_date }}" name="date_range"
                                    placeholder="{{ translate('Select Date') }}" data-time-picker="true"
                                    data-format="DD-MM-Y HH:mm:ss" data-separator=" to " autocomplete="off" required>
                            </div>
                        </div>
                        @php
                            $offer_product = \App\Models\OfferProduct::where('offer_id', $offer->id)
                                ->where('is_base_product', true)
                                ->first();
                        @endphp
                        <div class="form-group row">
                            <label class="col-sm-3 col-from-label" for="base_product">{{ translate('base_product') }}</label>
                            <div class="col-sm-9">
                                <select name="base_product" id="base_product" class="form-control aiz-selectpicker"
                                    required data-placeholder="{{ translate('Choose Product') }}" data-live-search="true"
                                    data-selected-text-format="count">
                                    @foreach (\App\Models\Product::where('published', 1)->where('approved', 1)->get() as $product)
                                        <option value="{{ $product->id }}" <?php if ($offer_product->product_id == $product->id) {
                                            echo 'selected';
                                        } ?>>{{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @php
                            $offer_products = \App\Models\OfferProduct::where('offer_id', $offer->id)
                                ->where('is_base_product', false)
                                ->get();
                        @endphp

                        <div class="form-group row">
                            <label class="col-sm-3 col-from-label" for="products">{{ translate('Products') }}</label>
                            <div class="col-sm-9">
                                <select name="products[]" id="products" class="form-control aiz-selectpicker" multiple
                                    required data-placeholder="{{ translate('Choose Products') }}" data-live-search="true"
                                    data-selected-text-format="count">
                                    @foreach (\App\Models\Product::where('published', 1)->where('approved', 1)->get() as $product)
                                        <option value="{{ $product->id }}" <?php if ($offer_products->contains('product_id', $product->id)) {
                                            echo 'selected';
                                        } ?>>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <br>

                        <br>
                        <br>

                        <div class="form-group" id="discount_table">

                        </div>

                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {

            $('#base_product').on('change', function() {
                get_offer_discount();
            });

            get_offer_discount();

            $('#products').on('change', function() {
                get_offer_discount();
            });

            function get_offer_discount() {
                var base_product_id = $('#base_product').val();
                var product_ids = $('#products').val();
                if (product_ids.length > 0) {
                    $.post('{{ route('offer_deals.product_discount_edit') }}', {
                        _token: '{{ csrf_token() }}',
                        product_ids: product_ids,
                        base_product_id: base_product_id,
                        offer_id: {{ $offer->id }}
                    }, function(data) {
                        $('#discount_table').html(data);
                        AIZ.plugins.fooTable();
                    });
                } else {
                    $('#discount_table').html(null);
                }
            }
        });
    </script>
@endsection
