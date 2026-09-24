@extends('backend.layouts.app')

@section('content')
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{ translate('New order') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('new.feat-order.trip.store') }}" method="POST">
                        @csrf
                        <input type="hidden" id="rep_discount_percentage" value="{{ old('rep_discount_percentage') }}">

                        <div class="flex-row">
                            <div class="d-flex justify-content-between">
                                <div class="form-group row col-6">
                                    <div class="col-4">
                                        <label class="control-label" for="customer">{{ translate('Customer') }}</label>
                                    </div>
                                    <div style="width: 100%;">
                                        <select name="customer_id" id="customer" class="form-control customer-search"
                                            required data-placeholder="{{ translate('Choose User') }}"
                                            data-live-search="true" data-selected-text-format="count">
                                            <option></option>
                                            @foreach (\App\Models\User::where('user_type', 'customer')->orderBy('name', 'desc')->get() as $user)
                                                <option value="{{ $user->id }}">
                                                    {{ $user->name . ' ' . $user->AccSysID }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group row col-6">
                                    <div class="col-4">
                                        <label class="control-label"
                                            for="delivery_date">{{ translate('Delivery Date') }}</label>
                                    </div>
                                    <div style="width: 100%;">
                                        <input type="date" placeholder="{{ translate('Delivery Date') }}"
                                            id="delivery_date" name="delivery_date" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row d-flex mx-1">
                                <div class="col-1">
                                    <label class="control-label" for="additional_info">{{ translate('Notes') }}</label>
                                </div>
                                <div style="width: 100%;">
                                    <textarea type="text" placeholder="{{ translate('Notes') }}" id="additional_info" name="additional_info"
                                        class="form-control"></textarea>
                                </div>
                            </div>

                        </div>


                        {{-- <div class="form-group mb-0 text-right">
                            <button type="button" id="fetch-all-data"
                                class="btn btn-success">{{ translate('Fetch All Data') }}</button>
                            <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                        </div> --}}
                    </form>

                    <table class="table" id="order-table">
                        <thead>
                            <tr>
                                <th>اسم المادة</th>
                                <th>رقم المادة</th>
                                <th>الكمية</th>
                                <th style="max-width:3rem">الواحدة</th>
                                <th>السعر</th>
                                <th>الاجمالي</th>
                                <th>ضريبة المبيعات</th>
                                <th>إجمالي الضريبة</th>
                                <th>الصافي</th>
                                <th style="max-width:5rem">بيان النفدة</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="order-row">
                                <td style="max-width:8rem">
                                    <select class="form-control product-search">
                                        <option hidden selected disabled>select your product</option>

                                        @foreach (\App\Models\Product::where('published', 1)->where('approved', 1)->orderBy('created_at', 'desc')->get() as $product)
                                            <option value="{{ $product->id }}">
                                                {{ $product->getTranslation('name') . ' ' . $product->serial }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="product-matId"></td>
                                <td contenteditable="true" class="product-qty" dir="ltr"></td>
                                <td class="product-unit" style="max-width:5rem">
                                    <div class="d-flex flex-row-reverse align-middle align-items-center"
                                        style="justify-content: space-between;">
                                        <div class="custom-control custom-switch d-flex align-items-center p-0"
                                            style="padding-right: 20px !important;">
                                            {{-- <span class="unit-label me-2">Piece</span> <!-- Move "Piece" before the switch --> --}}
                                            <input type="checkbox" class="custom-control-input unit-toggle" id="unit_0"
                                                name="unit_0" checked>
                                            <label class="custom-control-label" for="unit_0">
                                                <span class="unit-label ms-2">Box</span>
                                                <!-- Move "Box" after the switch -->
                                            </label>
                                        </div>
                                        <div>
                                            <span class="mx-2">Piece</span> <!-- Move "Box" after the switch -->
                                        </div>
                                    </div>
                                </td>
                                <td contenteditable="true" class="product-price" dir="ltr"></td>
                                <td class="product-total"></td>
                                <td class="product-tax"></td>
                                <td class="product-tax-total"></td>
                                <td class="product-net"></td>
                                <td class="product-info" contenteditable="true" style="max-width:5rem"></td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm delete-row">
                                        <i class="las la-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div>
                        <button type="button" class="btn btn-primary mb-3" style="width: 12rem" id="add-row">
                            <i class="fas fa-plus"></i> Add New Row
                        </button>
                    </div>
                    <div class="">
                        <button type="button" class="btn btn-primary mb-3" style="width: 12rem" id="save-order">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <span class="button-text">{{ translate('Save Order') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        #order-table td:focus {
            border: 2px solid black;
            /* Adds a black border when the cell is focused */
            outline: none;
            /* Optionally removes the default focus outline */
        }
    </style>
@endsection

@section('style')
@endsection


@section('script')
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            initializeSelect2();
            // Handle product selection
            $(document).on('change', '.product-search', function() {
                let selectedOption = $(this).find('option:selected');
                let row = $(this).closest('tr');

                let productId = selectedOption.val();

                $.ajax({
                    url: '{{ route('products.get-details', ['id' => ':id']) }}'.replace(':id',
                        productId),
                    type: 'GET',
                    success: function(product) {
                        row.find('.product-matId').text(product.mat_id);
                        row.find('.product-price').text(product.unit_price);
                        row.find('.product-tax').text(product.tax);
                        updateRowCalculations(row);
                    },
                    error: function(xhr, status, error) {
                        console.log(error);

                        console.error('Error fetching product details:', error);
                        alert('Error fetching product details. Please try again.');
                    }
                });
            });

            // Handle quantity changes
            $(document).on('input', '.product-qty', function() {
                updateRowCalculations($(this).closest('tr'));
            });

            $(document).on('input', '.product-price', function() {
                updateRowCalculations($(this).closest('tr'));
            });

            // Add new row button handler
            $('#add-row').click(function() {
                addNewRow();
            });

            // Delete row handler
            $(document).on('click', '.delete-row', function() {
                if ($('#order-table tbody tr').length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert('Cannot delete the last row');
                }
            });

            // Helper Functions
            function addNewRow() {
                let rowCount = $('#order-table tbody tr').length;
                let newRow = `
                    <tr class="order-row">
                        <td style="max-width:5rem">
                            <select class="form-control product-search">
                                <option hidden selected>select your product</option>
                                @foreach (\App\Models\Product::where('published', 1)->where('approved', 1)->orderBy('created_at', 'desc')->get() as $product)
                                    <option value="{{ $product->id }}">{{ $product->getTranslation('name') . ' ' . $product->serial }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="product-matId"></td>
                        <td contenteditable="true" class="product-qty" dir="ltr"></td>
                        <td class="product-unit" style="max-width:5rem">
                            <div class="d-flex flex-row-reverse align-middle align-items-center"
                                style="justify-content: space-between;">
                            <div class="custom-control custom-switch p-0" style="padding-right: 20px !important;">
                                <input type="checkbox" class="custom-control-input unit-toggle" id="unit_${rowCount}" name="unit_${rowCount}" checked>
                                <label class="custom-control-label" for="unit_${rowCount}">
                                    <span class="unit-label">Box</span>
                                </label>
                            </div>
                              <div>
                                            <span class="mx-2">Piece</span> <!-- Move "Box" after the switch -->
                                        </div>
                            </div>
                        </td>
                        <td contenteditable="true" class="product-price" dir="ltr"></td>
                        <td class="product-total"></td>
                        <td class="product-tax"></td>
                        <td class="product-tax-total"></td>
                        <td class="product-net"></td>
                        <td class="product-info" contenteditable="true"></td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm delete-row">
                                <i class="las la-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                $('#order-table tbody').append(newRow);
                initializeSelect2();

                let lastRow = $('#order-table tbody tr:last');
                let newProductSearch = lastRow.find('.product-search');

                setTimeout(() => {
                    newProductSearch.select2('open');
                    setTimeout(() => $('.select2-search__field')[0].focus(), 100);
                }, 100);
            }

            function updateRowCalculations(row) {
                let qty = parseFloat(row.find('.product-qty').text()) || 0;
                let price = parseFloat(row.find('.product-price').text()) || 0;
                let taxRate = parseFloat(row.find('.product-tax').text()) || 0;

                let total = qty * price;
                let taxAmount = (total * taxRate) / 100;
                let net = total + taxAmount;

                row.find('.product-total').text(total.toFixed(2));
                row.find('.product-tax-total').text(taxAmount.toFixed(2));
                row.find('.product-net').text(net.toFixed(2));
            }

            function initializeSelect2() {
                $('.rep-search:not(.select2-hidden-accessible)').select2({
                    width: '100%',
                    placeholder: "Select a Rep",
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        const terms = params.term.split(/\s+/);
                        const text = data.text.toLowerCase();
                        const match = terms.every(term => text.includes(term.toLowerCase()));

                        if (match) {
                            return data;
                        }
                        return null;
                    }
                });

                $('.product-search:not(.select2-hidden-accessible)').select2({
                    width: '100%',
                    placeholder: "Select a product",
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        const terms = params.term.split(/\s+/);
                        const text = data.text.toLowerCase();
                        const match = terms.every(term => text.includes(term.toLowerCase()));

                        if (match) {
                            return data;
                        }
                        return null;
                    }
                });

                $('.customer-search:not(.select2-hidden-accessible)').select2({
                    width: '100%',
                    placeholder: "Select a Customer",
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        const terms = params.term.split(/\s+/);
                        const text = data.text.toLowerCase();
                        const match = terms.every(term => text.includes(term.toLowerCase()));

                        if (match) {
                            return data;
                        }
                        return null;
                    }
                });
            }

            $('#save-order').click(function() {
                const $button = $(this);
                const $spinner = $button.find('.spinner-border');
                const $buttonText = $button.find('.button-text');

                $button.prop('disabled', true);
                $spinner.removeClass('d-none');
                $buttonText.text('Saving...');

                let products = [];
                $('#order-table tbody tr').each(function() {
                    let row = $(this);
                    let productId = row.find('.product-search').val();
                    let quantity = parseFloat(row.find('.product-qty').text()) || 0;
                    let price = parseFloat(row.find('.product-price').text()) || 0;
                    let tax = parseFloat(row.find('.product-tax').text()) || 0;
                    let info = row.find('.product-info').text() || '';
                    let unit = row.find('.unit-toggle').prop('checked') ? 'PerBox' : 'PerPiece';

                    if (productId &&
                        productId.toLowerCase() !== "select your product" &&
                        quantity > 0
                    ) {
                        products.push({
                            product_id: productId,
                            quantity: quantity,
                            price: price,
                            tax: tax,
                            info: info,
                            unit: unit
                        });
                    }
                });

                let formData = {
                    _token: '{{ csrf_token() }}',
                    rep_id: $('#rep').val(),
                    customer_id: $('#customer').val(),
                    delivery_date: $('#delivery_date').val(),
                    additional_info: $('#additional_info').val(),
                    products: products
                };

                $.ajax({
                    url: '{{ route('new.feat-order.trip.store') }}',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(formData),
                    success: function(response) {
                        window.location.href = response;
                        // window.location.href = '{{ route('all_orders.index') }}';
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('Failed to create order.');

                        $button.prop('disabled', false);
                        $spinner.addClass('d-none');
                        $buttonText.text('{{ translate('Save Order') }}');
                    }
                });
            });

            setTimeout(function() {
                $('.rep-search').select2('open');
                $('.select2-search__field')[0].focus();

            }, 100);

            $('.rep-search').on('select2:select', function(e) {
                $('.customer-search').select2('open');
                $('.select2-search__field')[0].focus();
            });

            $('.customer-search').on('select2:select', function(e) {
                $('#delivery_date')[0].focus();
            });

            $('#delivery_date').on('keydown', function(event) {
                if (event.key === 'Tab') {
                    setTimeout(function() {
                        let dateValue = $('#delivery_date').val();
                        $('#additional_info')[0].focus();
                    }, 100);
                }
            });

            function initialDate() {
                let today = new Date();
                let defaultDate = today.getFullYear() + '-' +
                    String(today.getMonth() + 1).padStart(2, '0') + '-' +
                    String(today.getDate()).padStart(2, '0');

                $('#delivery_date').val(defaultDate);
            }

            initialDate();

            $('#additional_info').on('keydown', function(e) {
                if (e.key === "Tab") {
                    e.preventDefault();
                    let firstProductSearch = $('.order-row:first .product-search');
                    firstProductSearch.select2('open');

                    setTimeout(function() {
                        $('.select2-search__field')[0].focus();
                    }, 100);
                }
            });

            $('#order-table').on('select2:close', '.product-search', function() {
                let currentRow = $(this).closest('.order-row');
                currentRow.find('.product-qty').focus();
            });

            $(document).on('keydown', '.product-qty', function(e) {
                if (e.key === "Tab") {
                    e.preventDefault();
                    $(this).closest('.order-row').find('.unit-toggle')[0].focus();
                }
            });

            $('#order-table').on('keydown', '.unit-toggle', function(e) {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    $(this).prop("checked", !$(this).prop("checked")).trigger("change");
                } else if (e.key === "ArrowLeft") {
                    e.preventDefault();
                    $(this).prop("checked", true).trigger("change");
                } else if (e.key === "ArrowRight") {
                    e.preventDefault();
                    $(this).prop("checked", false).trigger("change");
                }

                if (e.key === "Tab") {

                    e.preventDefault();
                    let currentRow = $(this).closest('.order-row');
                    let nextCell = currentRow.find('.product-price');
                    nextCell[0].focus();
                }
            });


            $(document).on('focus', '.product-price', function() {
                $(this).data('cleared', false);
            });
            $(document).on('keydown', '.product-price', function(e) {
                if (e.key === "Tab") {
                    e.preventDefault();
                    $(this).closest('.order-row').find('.product-info')[0].focus();
                }
                if (e.key >= "0" && e.key <= "9") {
                    let $cell = $(this);
                    if (!$cell.data('cleared')) {
                        $cell.text("");
                        $cell.data('cleared', true);
                    }
                }
            });

            $(document).on('keydown', '.product-info', function(e) {
                if (e.key === "Tab") {
                    e.preventDefault();

                    let currentRow = $(this).closest('.order-row');
                    let nextRow = currentRow.next('.order-row');

                    if (nextRow.length) {
                        nextRow.find('.product-search').select2('open');
                        setTimeout(() => $('.select2-search__field')[0].focus(), 100);
                    } else {
                        addNewRow();
                    }
                }
            });

            $(document).on('focus', '#order-table td', function() {
                let el = this;
                if (document.createRange && window.getSelection) {
                    let range = document.createRange();
                    let selection = window.getSelection();
                    range.selectNodeContents(el);
                    range.collapse(false);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            });

            function calculateTotalPrice() {
                let total = 0;
                $('#order-table tbody .product-net').each(function() {
                    let price = parseFloat($(this).text().trim()) || 0;
                    total += price;
                });

                let totalRow = $('#order-table tfoot #total-row');

                if (totalRow.length) {
                    totalRow.find('.grand-total').text(total.toFixed(2));
                } else {
                    $('#order-table').append(`
                        <tfoot>
                            <tr id="total-row">
                                <td colspan="3"><strong>Total Price:</strong></td>
                                <td class="grand-total"><strong>${total.toFixed(2)}</strong></td>
                            </tr>
                        </tfoot>
                    `);
                }
            }

            calculateTotalPrice();
            $(document).on('input', '.product-qty', function() {
                calculateTotalPrice();
            });
            $(document).on('input', '.product-price', function() {
                calculateTotalPrice();
            });
        });
    </script>
@endsection
