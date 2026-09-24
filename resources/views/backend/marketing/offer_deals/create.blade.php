@extends('backend.layouts.app')

@section('content')

<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{translate('Offer Deal Information')}}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('offer_deals.store') }}" method="get">
                    @csrf
                    <div class="form-group row">
                        <label class="col-sm-3 control-label" for="name">{{translate('Title')}}</label>
                        <div class="col-sm-9">
                            <input type="text" placeholder="{{translate('Title')}}" id="name" name="title" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label" for="signinSrEmail">{{translate('Banner')}} <small>(1920x500)</small></label>
                        <div class="col-md-9">
                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                <div class="input-group-prepend">
                                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                </div>
                                <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                <input type="hidden" name="banner" class="selected-files">
                            </div>
                            <div class="file-preview box sm">
                            </div>
                            <span class="small text-muted">{{ translate('This image is shown as cover banner in Offer deal details page.') }}</span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 control-label" for="start_date">{{translate('Date')}}</label>
                        <div class="col-sm-9">
                          <input type="text" class="form-control aiz-date-range" name="date_range" placeholder="{{ translate('Select Date') }}" data-time-picker="true" data-format="DD-MM-Y HH:mm:ss" data-separator=" to " autocomplete="off" required>
                        </div>
                    </div>

                    <div class="form-group row mb-3">
                        <label class="col-sm-3 control-label" for="base_product">{{translate('Base Product')}}</label>
                        <div class="col-sm-9">
                            <select name="base_product" id="base_product" class="form-control aiz-selectpicker" required data-placeholder="{{ translate('Choose Products') }}" data-live-search="true" data-selected-text-format="count">
                                <option ></option>
                                @foreach(\App\Models\Product::where('published', 1)->where('approved', 1)->orderBy('created_at', 'desc')->get() as $product)
                                    <option value="{{$product->id}}">{{ $product->getTranslation('name') }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-3">
                        <label class="col-sm-3 control-label" for="products">{{translate('Products')}}</label>
                        <div class="col-sm-9">
                            <select name="products[]" id="products" class="form-control aiz-selectpicker" multiple required data-placeholder="{{ translate('Choose Products') }}" data-live-search="true" data-selected-text-format="count">
                                @foreach(\App\Models\Product::where('published', 1)->where('approved', 1)->orderBy('created_at', 'desc')->get() as $product)
                                    <option value="{{$product->id}}">{{ $product->getTranslation('name') }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <br>

                    <div class="form-group" id="discount_table_for_base_product">

                    </div>

                    <br>
                    <br>

                    <div class="form-group" id="discount_table">

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
        $(document).ready(function(){
            $('#base_product').on('change', function(){
                var product_id = $('#base_product').val();
                console.log(product_id);
                if(product_id.length > 0){
                    $.get('{{ route('offer_deals.base_product_discount') }}', {_token:'{{ csrf_token() }}', product_id:product_id}, function(data){
                        $('#discount_table_for_base_product').html(data);
                        AIZ.plugins.fooTable();
                    });
                }
                else{
                    $('#discount_table_for_base_product').html(null);
                }
            });
            $('#products').on('change', function(){
                var product_ids = $('#products').val();
                console.log(product_ids);

                if(product_ids.length > 0){
                    $.get('{{ route('offer_deals.product_discount') }}', {_token:'{{ csrf_token() }}', product_ids:product_ids}, function(data){
                        $('#discount_table').html(data);
                        AIZ.plugins.fooTable();
                    });
                }
                else{
                    $('#discount_table').html(null);
                }
            });
        });
    </script>
@endsection
