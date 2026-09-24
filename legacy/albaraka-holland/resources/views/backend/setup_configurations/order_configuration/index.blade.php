@extends('backend.layouts.app')

@section('content')

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{translate('Minimum Order Amount Settings')}}</h5>
            </div>
            <form action="{{ route('business_settings.update') }}" method="POST" enctype="multipart/form-data">
              <div class="card-body">
                   @csrf
                    <div class="form-group row">
                        <div class="col-md-4">
                            <label class="control-label">{{translate('Minimum Order Amount Check')}}</label>
                        </div>
                        <div class="col-md-8">
                            <label class="aiz-switch aiz-switch-success mb-0">
                                <input type="hidden" name="types[]" value="minimum_order_amount_check">
                                <input value="1" name="minimum_order_amount_check" type="checkbox" @if (get_setting('minimum_order_amount_check') == 1)
                                    checked
                                @endif>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group row">
                        <input type="hidden" name="types[]" value="minimum_order_amount">
                        <div class="col-md-4">
                            <label class="control-label">{{translate('Set Minimum Order Amount')}}</label>
                        </div>
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="minimum_order_amount" value="{{ get_setting('minimum_order_amount') }}" placeholder="{{ translate('Minimum Order Amount') }}" required>
                        </div>
                    </div>
                    <div class="form-group row" id="product">
                        <input type="hidden" name="types[]" value="default_specific_product">
                        <div class="col-md-4">
                            <label class="control-label">{{translate('Default Product')}}</label>
                        </div>
                        <div class="col-md-8">
                            <input type="text" id="product_search" class="form-control" name="default_specific_product" value="{{ get_setting('default_specific_product') }}" placeholder="{{ translate('Search for A Product By MAT ID') }}" required>
                            <label class="control-label mt-2" id="selected_product_name">{{ translate('Selected Product:') }}  {{ \App\Models\Product::where('mat_id', get_setting('default_specific_product'))->first()->name ?? null }}</label>
                            <div id="search_results" class="mt-4"></div>
                        </div>
                    </div>
                    <div class="form-group mb-0 text-right">
                        <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                    </div>
              </div>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{translate('Default Representative')}}</h5>
            </div>
            <form action="{{ route('business_settings.update') }}" method="POST" enctype="multipart/form-data">
                <div class="card-body">
                    @csrf
                    <div class="form-group row">
                        <div class="col-md-4">
                            <label class="control-label">{{translate('Default Representative Check')}}</label>
                        </div>
                        <div class="col-md-8">
                            <label class="aiz-switch aiz-switch-success mb-0">
                                <input type="hidden" name="types[]" value="default_representative_check">
                                <input value="1" name="default_representative_check" type="checkbox"
                                @if (get_setting('default_representative_check') == 1)
                                    checked
                                @endif>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group row" id="product">
                        <input type="hidden" name="types[]" value="default_representative_id">
                        <div class="col-md-4">
                            <label class="control-label">{{translate('Default Representative')}}</label>
                        </div>
                        <div class="col-md-8">
                            <select class="form-control" name="default_representative_id" data-live-search="true" placeholder="{{ translate('Search for A User By Name') }}" required>
                                <option value="">{{ translate('Select Representative') }}</option>
                                @foreach (\App\Models\User::where('is_rep', true)->orderBy('id', 'desc')->get() as $rep)
                                    <option value="{{ $rep->id }}" {{ get_setting('default_representative_id') == $rep->id ? 'selected' : '' }}>
                                        {{ $rep->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-0 text-right">
                        <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function () {
        $('#product_search').on('input', function () {
            var query = $(this).val();

            if (query.length >= 4) {
                $.ajax({
                    url: "{{ route('order_configuration.product-search') }}",
                    method: 'GET',
                    data: { query: query },
                    success: function (data) {
                        displaySearchResults(data);
                    },
                    error: function (xhr, status, error) {
                        console.error(error);
                    }
                });
            }
        });

        function displaySearchResults(results) {
            var resultsContainer = $('#search_results');
            resultsContainer.empty();
            resultsContainer.append('<label class="control-label mt-2">Search Results:</label>');

            $.each(results, function (index, product) {
                var resultDiv = $('<div class="search-result m-3" data-product-id="' + product.mat_id + '">' + product.name + '</div>');
                resultDiv.css('cursor', 'pointer');

                resultsContainer.append(resultDiv);
            });

            $('.search-result').on('click', function () {
                var matId = $(this).data('product-id');
                var productName = $(this).text();

                $('#product_search').val(matId);
                $('#selected_product_name').text('Selected Product:  ' + productName);

                resultsContainer.empty();
            });
        }
    });
</script>
