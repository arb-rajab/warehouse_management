@extends('backend.layouts.app')

@section('content')
    @php
        // CoreComponentRepository::instantiateShopRepository();
        // CoreComponentRepository::initializeCache();
    @endphp

    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <h1 class="h3">{{ translate('All products') }}</h1>
            </div>
            @if ($type != 'Seller' && auth()->user()->can('add_new_product'))
                <div class="col text-right">
                    <a href="{{ route('products.create') }}" class="btn btn-circle btn-info">
                        <span>{{ translate('Add New Product') }}</span>
                    </a>
                </div>


                <div class="col">
                    <button id="runCommandButton" class="btn btn-circle btn-primary">
                        <span>{{ translate('Fetch/Update Products from API') }}</span>
                    </button>
                </div>
            @endif
        </div>
    </div>
    <br>

    <div class="card">
        <form class="" id="sort_products" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-md-0 h6">{{ translate('All Product') }}</h5>
                </div>

                @can('product_delete')
                    <div class="dropdown mb-2 mb-md-0">
                        <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                            {{ translate('Bulk Action') }}
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" onclick="bulk_delete()">
                                {{ translate('Delete selection') }}</a>
                        </div>
                    </div>
                @endcan
                {{--
            @if ($type == 'Seller')
            <div class="col-md-2 ml-auto">
                <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" id="user_id" name="user_id" onchange="sort_products()">
                    <option value="">{{ translate('All Sellers') }}</option>
                    @foreach (App\Models\User::where('user_type', '=', 'seller')->get() as $key => $seller)
                        <option value="{{ $seller->id }}" @if ($seller->id == $seller_id) selected @endif>
                            {{ $seller->shop->name }} ({{ $seller->name }})
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            @if ($type == 'All')
            <div class="col-md-2 ml-auto">
                <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" id="user_id" name="user_id" onchange="sort_products()">
                    <option value="">{{ translate('All Sellers') }}</option>
                        @foreach (App\Models\User::where('user_type', '=', 'admin')->orWhere('user_type', '=', 'seller')->get() as $key => $seller)
                            <option value="{{ $seller->id }}" @if ($seller->id == $seller_id) selected @endif>{{ $seller->name }}</option>
                        @endforeach
                </select>
            </div>
            @endif
             --}}
                <div class="dropdown mb-2 mb-md-0 ml-1">
                    <button type="button" class="btn border menu-dropdown dropdown-toggle" data-toggle="dropdown">
                        {{ translate('Filter') }}
                    </button>
                    <div class="dropdown-menu dropdown-menu-right dropdown-menu-sub-dropdown"
                        onclick="stopPropagation(event)">
                        <div class="px-3 py-2">
                            <div class="d-flex align-items-center">
                                <span class="fs-5 text-dark fw-bold mr-2">{{ translate('Filter Options') }}</span>
                            </div>
                        </div>


                        <div class="separator border-gray-200"></div>

                        <div class="px-3 py-2">
                            <div class="mb-10 py-2">
                                <div class="py-1">
                                    <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                        href="{{ route('products.all', ['allProducts' => true]) }}"
                                        title="{{ translate('All Products') }}">{{ translate('All Products') }}
                                    </a>

                                    <div class="separator border-gray-200 py-2"></div>

                                    <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                        href="{{ route('products.all', ['allActive' => true]) }}"
                                        title="{{ translate('All Active') }}">{{ translate('All Active') }}
                                    </a>
                                    <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                        href="{{ route('products.all', ['allForSale' => true]) }}"
                                        title="{{ translate('All For Sale') }}">{{ translate('All For Sale') }}
                                    </a>
                                    <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                        href="{{ route('products.all', ['allFeatured' => true]) }}"
                                        title="{{ translate('All Featured') }}">{{ translate('All Featured') }}
                                    </a>
                                    <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                        href="{{ route('products.all', ['allDeleted' => true]) }}"
                                        title="{{ translate('All Deleted') }}">{{ translate('All Deleted') }}
                                    </a>
                                </div>

                                <div class="separator border-gray-200 py-2"></div>

                                <div class="py-1">
                                    <label class="aiz-switch aiz-switch-success px-2 " style="font-size:20px;"
                                        for="active">{{ translate('active') }}
                                        <input type="checkbox" name="active" id="active" class="mr-2 check-one filter"
                                            @if (request()->has('active')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>




                                <div class="py-1">
                                    <label class="aiz-switch aiz-switch-success  px-2 " style="font-size:20px;"
                                        for="for_sale">{{ translate('For Sale') }}
                                        <input type="checkbox" name="for_sale" id="for_sale" class="mr-2 check-one filter"
                                            @if (request()->has('for_sale')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="py-1">
                                    <label class="aiz-switch aiz-switch-success  px-2 " style="font-size:20px;"
                                        for="featured">{{ translate('featured') }}
                                        <input type="checkbox" name="featured" id="featured" class="mr-2 check-one filter"
                                            @if (request()->has('featured')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                  <div class="py-1">
                                    <label class="aiz-switch aiz-switch-success px-2 " style="font-size:20px;"
                                        for="deleted">{{ translate('deleted') }}
                                        <input type="checkbox" name="deleted" id="deleted" class="mr-2 check-one filter"
                                            @if (request()->has('deleted')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>



                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">{{ translate('Apply') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 ml-auto">
                    <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="category_id"
                        id="type" onchange="sort_products()">
                        <option value="">{{ translate('Choose Category') }}</option>
                        @foreach (\App\Models\Category::all() as $category)
                            <option value="{{ $category->id }}"
                                @isset($category_id) @if ($category_id == $category->id) selected @endif @endisset>
                                {{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 ml-auto">
                    <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="type" id="type"
                        onchange="sort_products()">
                        <option value="">{{ translate('Sort By') }}</option>
                        <option value="rating,desc"
                            @isset($col_name, $query) @if ($col_name == 'rating' && $query == 'desc') selected @endif @endisset>
                            {{ translate('Rating (High > Low)') }}</option>
                        <option value="rating,asc"
                            @isset($col_name, $query) @if ($col_name == 'rating' && $query == 'asc') selected @endif @endisset>
                            {{ translate('Rating (Low > High)') }}</option>
                        <option
                            value="num_of_sale,desc"@isset($col_name, $query) @if ($col_name == 'num_of_sale' && $query == 'desc') selected @endif @endisset>
                            {{ translate('Num of Sale (High > Low)') }}</option>
                        <option
                            value="num_of_sale,asc"@isset($col_name, $query) @if ($col_name == 'num_of_sale' && $query == 'asc') selected @endif @endisset>
                            {{ translate('Num of Sale (Low > High)') }}</option>
                        <option
                            value="unit_price,desc"@isset($col_name, $query) @if ($col_name == 'unit_price' && $query == 'desc') selected @endif @endisset>
                            {{ translate('Base Price (High > Low)') }}</option>
                        <option
                            value="unit_price,asc"@isset($col_name, $query) @if ($col_name == 'unit_price' && $query == 'asc') selected @endif @endisset>
                            {{ translate('Base Price (Low > High)') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-group mb-0">
                        <input type="text" class="form-control form-control-sm" id="search"
                            name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                            placeholder="{{ translate('Type & Enter') }}">
                    </div>
                </div>
            </div>

            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            @if (auth()->user()->can('product_delete'))
                                <th>
                                    <div class="form-group">
                                        <div class="aiz-checkbox-inline">
                                            <label class="aiz-checkbox">
                                                <input type="checkbox" class="check-all">
                                                <span class="aiz-square-check"></span>
                                            </label>
                                        </div>
                                    </div>
                                </th>
                            @else
                                <th data-breakpoints="lg">#</th>
                            @endif
                            <th>{{ translate('Name') }}</th>
                            {{-- @if ($type == 'Seller' || $type == 'All')
                            <th data-breakpoints="lg">{{translate('Added By')}}</th>
                        @endif --}}
                            <th data-breakpoints="md">{{ translate('Material Id') }}</th>
                            <th data-breakpoints="md">{{ translate('Unit') }}</th>
                            <th data-breakpoints="sm">{{ translate('Info') }}</th>
                            <th data-breakpoints="md">{{ translate('Total Stock') }}</th>
                            <th data-breakpoints="md">{{ translate('Category') }}</th>
                            {{-- <th data-breakpoints="lg">{{translate('Todays Deal')}}</th> --}}
                            <th data-breakpoints="lg">{{ translate('For Sale?') }}</th>
                            <th data-breakpoints="lg">{{ translate('Active') }}</th>
                            @if (get_setting('product_approve_by_admin') == 1 && $type == 'Seller')
                                <th data-breakpoints="lg">{{ translate('Approved') }}</th>
                            @endif
                            <th data-breakpoints="lg">{{ translate('Featured') }}</th>
                            <th data-breakpoints="lg">{{ translate('Deleted From Otajer') }}
                            <th data-breakpoints="sm" class="text-right">{{ translate('Options') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $key => $product)
                            <tr>
                                @if (auth()->user()->can('product_delete'))
                                    <td>
                                        <div class="form-group d-inline-block">
                                            <label class="aiz-checkbox">
                                                <input type="checkbox" class="check-one" name="id[]"
                                                    value="{{ $product->id }}">
                                                <span class="aiz-square-check"></span>
                                            </label>
                                        </div>
                                    </td>
                                @else
                                    <td>{{ $key + 1 + ($products->currentPage() - 1) * $products->perPage() }}</td>
                                @endif
                                <td>
                                    <div class="row gutters-5 w-200px w-md-300px mw-100">
                                        <div class="col-auto">
                                            <img src="{{ uploaded_asset($product->thumbnail_img) }}" alt="Image"
                                                class="size-50px img-fit">
                                        </div>
                                        <div class="col">
                                            <span
                                                @if($product->deleted_from_api)
                                                    class="text-danger text-truncate-2">{{ $product->name . $product->ar_name }}</span>
                                                @else
                                                class="text-muted text-truncate-2">{{ $product->name . $product->ar_name }}</span>
                                                @endif
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    {{ $product->mat_id }}
                                </td>

                                <td>
                                    {{ $product->api_unit_name ."(" . $product->unit_equal . ")" }}
                                </td>

                                {{-- @if ($type == 'Seller' || $type == 'All')
                            <td>{{ optional($product->user)->name }}</td>
                        @endif --}}
                                <td>
                                    <strong>{{ translate('Num of Sale') }}:</strong> {{ $product->num_of_sale }}
                                    {{ translate('times') }} </br>
                                    <strong>{{ translate('Base Price') }}:</strong>
                                    {{ single_price($product->unit_price) }} </br>
                                    {{-- <strong>{{ translate('Rating') }}:</strong> {{ $product->rating }} </br> --}}
                                    <strong>{{ translate('Profit') }}:</strong> {{ single_price($product->profit()) }} </br>
                                </td>
                                <td>
                                    @php
                                        $qty = 0;
                                        if ($product->variant_product) {
                                            foreach ($product->stocks as $key => $stock) {
                                                $qty += $stock->qty;
                                                echo $stock->variant . ' - ' . $stock->qty . '<br>';
                                            }
                                        } else {
                                            //$qty = $product->current_stock;
                                            $qty = optional($product->stocks->first())->qty;
                                            echo $qty;
                                        }
                                    @endphp
                                    @if ($qty <= $product->low_stock_quantity)
                                        <span class="badge badge-inline badge-danger">Low</span>
                                    @endif
                                </td>

                                <td>
                                    {{ $product?->category?->getTranslation('name') }}
                                </td>
                                {{-- <td>


                            <label class="aiz-switch aiz-switch-success mb-0">
                                <input onchange="update_todays_deal(this)" value="{{ $product->id }}" type="checkbox" <?php if ($product->todays_deal == 1) {
                                    echo 'checked';
                                } ?> >
                                <span class="slider round"></span>
                            </label>
                        </td> --}}
                                <td>
                                    <label class="aiz-switch aiz-switch-success mb-0">
                                        <input onchange="update_status(this)" value="{{ $product->id }}"
                                            type="checkbox" <?php if ($product->for_sale == 1) {
                                                echo 'checked';
                                            } ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </td>

                                <td>
                                    <label class="aiz-switch aiz-switch-success mb-0">
                                        <input onchange="update_published(this)" value="{{ $product->id }}"
                                            type="checkbox" <?php if ($product->published == 1) {
                                                echo 'checked';
                                            } ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </td>
                                @if (get_setting('product_approve_by_admin') == 1 && $type == 'Seller')
                                    <td>
                                        <label class="aiz-switch aiz-switch-success mb-0">
                                            <input onchange="update_approved(this)" value="{{ $product->id }}"
                                                type="checkbox" <?php if ($product->approved == 1) {
                                                    echo 'checked';
                                                } ?>>
                                            <span class="slider round"></span>
                                        </label>
                                    </td>
                                @endif
                                <td>
                                    <label class="aiz-switch aiz-switch-success mb-0">
                                        <input onchange="update_featured(this)" value="{{ $product->id }}"
                                            type="checkbox" <?php if ($product->featured == 1) {
                                                echo 'checked';
                                            } ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </td>
                                <td>
                                    <label class="aiz-switch aiz-switch-success mb-0">
                                        @if ($product->deleted_from_api)
                                            <p class='text-danger fs-4'>Deleted</p>
                                        @endif

                                    </label>
                                </td>
                                <td class="text-right">
                                    <a class="btn btn-soft-success btn-icon btn-circle btn-sm"
                                        href="{{ route('product', $product->slug) }}" target="_blank"
                                        title="{{ translate('View') }}">
                                        <i class="las la-eye"></i>
                                    </a>
                                    @can('product_edit')
                                        @if ($type == 'Seller')
                                            <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                                                href="{{ route('products.seller.edit', ['id' => $product->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                                                title="{{ translate('Edit') }}">
                                                <i class="las la-edit"></i>
                                            </a>
                                        @else
                                            <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                                                href="{{ route('products.admin.edit', ['id' => $product->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                                                title="{{ translate('Edit') }}">
                                                <i class="las la-edit"></i>
                                            </a>
                                        @endif
                                    @endcan
                                    @can('product_duplicate')
                                        <a class="btn btn-soft-warning btn-icon btn-circle btn-sm"
                                            href="{{ route('products.duplicate', ['id' => $product->id, 'type' => $type]) }}"
                                            title="{{ translate('Duplicate') }}">
                                            <i class="las la-copy"></i>
                                        </a>
                                    @endcan
                                    @can('product_delete')
                                        <a href="#"
                                            class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                                            data-href="{{ route('products.destroy', $product->id) }}"
                                            title="{{ translate('Delete') }}">
                                            <i class="las la-trash"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                    {{ $products->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>
@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection


@section('script')
    <script type="text/javascript">
        $(document).on("change", ".check-all", function() {
            if (this.checked) {
                // Iterate each checkbox
                $('.check-one:checkbox').each(function() {
                    this.checked = true;
                });
            } else {
                $('.check-one:checkbox').each(function() {
                    this.checked = false;
                });
            }

        });

        $(document).ready(function() {
            //$('#container').removeClass('mainnav-lg').addClass('mainnav-sm');
        });

        function update_todays_deal(el) {
            if (el.checked) {
                var status = 1;
            } else {
                var status = 0;
            }
            $.post('{{ route('products.todays_deal') }}', {
                _token: '{{ csrf_token() }}',
                id: el.value,
                status: status
            }, function(data) {
                if (data == 1) {
                    AIZ.plugins.notify('success', '{{ translate('Todays Deal updated successfully') }}');
                } else {
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }

        function update_status(el) {
            if (el.checked) {
                var status = 1;
            } else {
                var status = 0;
            }
            $.post('{{ route('products.update_sale') }}', {
                _token: '{{ csrf_token() }}',
                id: el.value,
                status: status
            }, function(data) {
                if (data == 1) {
                    AIZ.plugins.notify('success', '{{ translate('Product Status updated') }}');
                } else {
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }

        function update_published(el) {
            if (el.checked) {
                var status = 1;
            } else {
                var status = 0;
            }
            $.post('{{ route('products.published') }}', {
                _token: '{{ csrf_token() }}',
                id: el.value,
                status: status
            }, function(data) {
                if (data == 1) {
                    AIZ.plugins.notify('success', '{{ translate('Published products updated successfully') }}');
                } else {
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }

        function update_approved(el) {
            if (el.checked) {
                var approved = 1;
            } else {
                var approved = 0;
            }
            $.post('{{ route('products.approved') }}', {
                _token: '{{ csrf_token() }}',
                id: el.value,
                approved: approved
            }, function(data) {
                if (data == 1) {
                    AIZ.plugins.notify('success', '{{ translate('Product approval update successfully') }}');
                } else {
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }

        function update_featured(el) {
            if (el.checked) {
                var status = 1;
            } else {
                var status = 0;
            }
            $.post('{{ route('products.featured') }}', {
                _token: '{{ csrf_token() }}',
                id: el.value,
                status: status
            }, function(data) {
                if (data == 1) {
                    AIZ.plugins.notify('success', '{{ translate('Featured products updated successfully') }}');
                } else {
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }

        function sort_products(el) {
            $('#sort_products').submit();
        }

        function bulk_delete() {
            var data = new FormData($('#sort_products')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('bulk-product-delete') }}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response == 1) {
                        location.reload();
                    }
                }
            });
        }

        $(document).ready(function() {
            $('#runCommandButton').on('click', function() {
                // Disable the button
                $(this).prop('disabled', true);
                // Make an AJAX request to run the command
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: "{{ route('products.fetch') }}", // Replace with the actual route URL
                    type: 'POST',
                    success: function(response) {
                        // Enable the button after the command is done
                        $('#runCommandButton').prop('disabled', false);
                        AIZ.plugins.notify('success',
                            '{{ translate('Fetching Products... Please Wait for 30 minutes') }}'
                        );

                    },
                    error: function() {
                        // Enable the button if an error occurs
                        $('#runCommandButton').prop('disabled', false);
                        AIZ.plugins.notify('danger',
                            '{{ translate('Already updating products, try again after 30 minutes ') }}'
                        );

                    }
                });
            });
        });
    </script>

    <script>
        function stopPropagation(event) {
            event.stopPropagation();
        }
    </script>

    <script>
        $(document).ready(function() {

            $('#all_products').change(function() {
                // Disable or enable other checkboxes based on the "all" checkbox state
                $('.filter').not(this).prop('disabled', this.checked);

                // Remove the 'checked' attribute from other checkboxes
                if (this.checked) {
                    $('.check-one').not(this).prop('checked', false);
                }
            });
        });
    </script>
@endsection
