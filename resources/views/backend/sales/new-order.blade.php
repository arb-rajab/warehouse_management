@extends('backend.layouts.app')

@section('content')
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{ translate('New order') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('feat-order.trip.store') }}" method="post">
                        @csrf

                        <input type="hidden" id="rep_discount_percentage" value="{{ old('rep_discount_percentage') }}">

                        <div class="form-group row mb-3">
                            <label class="col-sm-3 control-label" for="customer">{{ translate('Customer') }}</label>
                            <div class="col-sm-9">
                                <select name="customer_id" id="customer" class="form-control aiz-selectpicker" required
                                    data-placeholder="{{ translate('Choose User') }}" data-live-search="true"
                                    data-selected-text-format="count">
                                    <option></option>
                                    @foreach (\App\Models\User::where('user_type', 'customer')->orderBy('name', 'desc')->get() as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} - {{$user->AccSysID}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row mb-3">
                            <label class="col-sm-3 control-label" for="products">{{ translate('Products') }}</label>
                            <div class="col-sm-9">
                                <select name="products[]" id="products" class="form-control aiz-selectpicker" multiple
                                    required data-placeholder="{{ translate('Choose Products') }}" data-live-search="true"
                                    data-selected-text-format="count">
                                    @foreach (\App\Models\Product::where('published', 1)->where('approved', 1)->orderBy('created_at', 'desc')->get() as $product)
                                        <option value="{{ $product->id }}">{{ $product->getTranslation('name') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <br>

                        <div class="form-group" id="all_data_table">

                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 control-label" for="additional_info">{{ translate('Notes') }}</label>
                            <div class="col-sm-9">
                                <textarea type="text" placeholder="{{ translate('Notes') }}" id="additional_info" name="additional_info"
                                    class="form-control"></textarea>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 control-label"
                                for="delivery_date">{{ translate('Delivery Date') }}</label>
                            <div class="col-sm-9">
                                <input type="date" placeholder="{{ translate('Delivery Date') }}" id="delivery_date"
                                    name="delivery_date" class="form-control">
                            </div>
                        </div>


                        <div class="form-group mb-0 text-right">
                            <button type="button" id="fetch-all-data"
                                class="btn btn-success">{{ translate('Fetch All Data') }}</button>
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
            // Function to fetch data via AJAX
            function fetchData(url, data, targetElement) {
                $(targetElement).html(
                    '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
                $.get(url, data, function(response) {
                    $(targetElement).html(response);
                    AIZ.plugins.fooTable(); // Initialize fooTable if needed
                }).fail(function(error) {
                    $(targetElement).html(
                        '<div class="text-danger">Failed to load data. Please try again.</div>');
                });
            }

            // Fetch all data when the "Fetch All Data" button is clicked
            $('#fetch-all-data').on('click', function() {
                const product_ids = $('#products').val();
                const customer_id = $('#customer').val();
                // const rep_id = $('#rep').val();

                fetchData('{{ route('feat-order.trip.needed-data') }}', {
                    _token: '{{ csrf_token() }}',
                    user_id: customer_id,
                    // rep_id: rep_id,
                    product_ids: product_ids
                }, '#all_data_table');
            });
        });
    </script>
@endsection
