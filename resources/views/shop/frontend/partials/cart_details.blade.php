<div class="container">
    @if ($carts && count($carts) > 0)
        <div class="row">
            <div class="col-10 mx-auto">
                <div class="border bg-white p-3 p-lg-4 text-left">
                    <div class="mb-4">
                        <!-- Headers -->
                        <div class="row gutters- d-none d-lg-flex border-bottom mb-3 pb-3 text-secondary fs-12">

                            <div class="col-md-4 fw-600">{{ translate('Product') }}</div>
                            <div class="col-md-1 fw-600">{{ translate('Qty') }}</div>
                            @if (Auth::check() && Auth::user()->admin_verified)
                                <div class="col-md-1 fw-600">{{ translate('Price') }}</div>

                                {{-- <div class="col-md-1 fw-600">{{ translate('Tax')}}</div> --}}
                                <div class="col-md-1 fw-600">{{ translate('Total') }}</div>
                            @endif
                            <div class="col-md-1 fw-600">{{ translate('Remove') }}</div>
                            <div class="col-md-1 fw-600 text-center">{{ translate('Variation') }}</div>
                            <div class="col-md-1 fw-600">{{ translate('Notes') }}</div>

                            @if(Auth::check() && Auth::user()->is_rep && Auth::user()->representativePackage)
                            <div class="col-md-1 fw-600">{{ translate('Change Price') }}</div>
                            <div class="col-md-1 fw-600">{{ translate('Edited Price') }}</div>
                            @endif
                        </div>
                        @php
                            $total = 0;
                            $edited_total = 0; // when the rep change the price
                            $currentPage = request()->get('page', 1);
                            $perPage = 10;
                            $offset = ($currentPage - 1) * $perPage;
                            $cartsArray = $carts->toArray();
                            $paginatedCarts = array_slice($cartsArray, $offset, $perPage, true);
                            $paginator = new \Illuminate\Pagination\LengthAwarePaginator($paginatedCarts, count($carts), $perPage, $currentPage);
                            $paginator->setPath(request()->url());
                        @endphp

                        <!-- Cart Items -->
                        <ul class="list-group list-group-flush">
                            @foreach ($paginator as $key => $cartItem)
                                @php
                                    $product = \App\Models\Product::find($cartItem['product_id']);
                                    $product_stock = $product->stocks->where('variant', $cartItem['variation'])->first();
                                    $total = $total + cart_product_price($cartItem, $product, false) * $cartItem['quantity'];
                                    if($cartItem['rep_price'] > 0){
                                        $edited_total = $edited_total + $cartItem['rep_price'] * $cartItem['quantity'];
                                    }
                                    else{
                                        $edited_total = $edited_total + cart_product_price($cartItem, $product, false) * $cartItem['quantity'];
                                    }

                                    $product_name_with_choice = $product->getTranslation('name');
                                    if ($cartItem['variation'] != null) {
                                        $product_name_with_choice = $product_name_with_choice . ' - ' . $cartItem['variation'];
                                    }
                                @endphp
                                <li class="list-group-item px-0">
                                    <div class="row gutters-5 align-items-center">

                                        <!-- Product Image & name -->
                                        <div class="col-md-4 d-flex align-items-center mb-2 mb-md-0">
                                            {{-- <span class="mr-2 ml-0">
                                            <img src="{{ uploaded_asset($product->thumbnail_img) }}"
                                                class="img-fit size-70px"
                                                alt="{{ $product->getTranslation('name')  }}"
                                                onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                                        </span> --}}
                                            <span class="fs-14">{{ $product_name_with_choice }}</span>
                                        </div>
                                        <!-- Quantity -->
                                        <div class="col-md-1 order-1 order-md-0">
                                            @if ($product->auction_product == 0)
                                                <div
                                                    class="d-flex flex-column align-items-start aiz-plus-minus mr-2 ml-0">
                                                    {{-- <button
                                                    class="btn col-auto btn-icon btn-sm btn-circle btn-light"
                                                    type="button" data-type="plus"
                                                    data-field="quantity[{{ $cartItem['id'] }}]">
                                                    <i class="las la-plus"></i>
                                                </button> --}}
                                                    <input type="number" name="quantity[{{ $cartItem['id'] }}]"
                                                        class="col border-0 text-left px-0 flex-grow-1 fs-14 input-number"
                                                        placeholder="1" value="{{ $cartItem['quantity'] }}"
                                                        min="{{ $product->min_qty }}" max="{{ $product_stock->qty ?? null }}"
                                                        {{-- onchange="updateQuantity({{ $cartItem['id'] }}, this)" --}} disabled
                                                        style="padding-left:0.75rem !important;">
                                                    {{-- <button
                                                    class="btn col-auto btn-icon btn-sm btn-circle btn-light"
                                                    type="button" data-type="minus"
                                                    data-field="quantity[{{ $cartItem['id'] }}]">
                                                    <i class="las la-minus"></i>
                                                </button> --}}
                                                </div>
                                            @elseif($product->auction_product == 1)
                                                <span class="fw-700 fs-14">1</span>
                                            @endif
                                        </div>
                                        @if (Auth::check() && Auth::user()->admin_verified)
                                            <!-- Price -->
                                            <div class="col-md-1 order-2 order-md-0 my-3 my-md-0">
                                                <span
                                                    class="opacity-60 fs-12 d-block d-md-none">{{ translate('Price') }}</span>
                                                <span
                                                    class="fw-700 fs-14">{{ cart_product_price($cartItem, $product, true, false) }}</span>
                                            </div>
                                            <!-- Tax -->
                                            {{-- <div class="col-md col-4 order-3 order-md-0 my-3 my-md-0">
                                        <span class="opacity-60 fs-12 d-block d-md-none">{{ translate('Tax')}}</span>
                                        <span class="fw-700 fs-14">{{ cart_product_tax($cartItem, $product) }}</span>
                                    </div> --}}
                                            <!-- Total -->
                                            <div class="col-md-1 order-4 order-md-0 my-3 my-md-0">
                                                <span
                                                    class="opacity-60 fs-12 d-block d-md-none">{{ translate('Total') }}</span>
                                                <span
                                                    class="fw-700 fs-16 text-primary">{{ single_price(cart_product_price($cartItem, $product, false) * $cartItem['quantity']) }}</span>
                                            </div>
                                        @endif
                                        <!-- Remove From Cart -->

                                        <div class="col-md-1 order-5 order-md-0 text-center">
                                            <a href="javascript:void(0)"
                                                onclick="removeFromCartView(event, {{ $cartItem['id'] }})"
                                                class="btn btn-icon btn-sm btn-soft-primary bg-soft-warning hov-bg-primary btn-circle">
                                                <i class="las la-trash fs-16"></i>
                                            </a>
                                        </div>

                                        <!-- Variation -->

                                        <div class="col-md-1 order-5 order-md-0 text-center">
                                            @if (isset($cartResults[$cartItem['id']]))
                                                @foreach ($cartResults[$cartItem['id']] as $key => $cart)
                                                    <div class="fw-400 fs-16">
                                                        {{-- {{ $cart['name'] . ' : ' . $cart['value'] }} --}}
                                                        {{ $key . ' : ' . $cart }}
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>

                                        <!-- Item Notes -->

                                        <div class="col-md-1 order-5 order-md-0 text-center">
                                            <a href="#" onclick="openUpdateModal(this)" data-item-id="{{ $cartItem['id'] }}" data-old-notes="{{ $cartItem['item_notes'] }}" class="btn btn-soft-warning btn-icon btn-circle btn-sm" title="{{ translate('Update Item Notes') }}">
                                                <i class="las la-edit"></i>
                                            </a>
                                        </div>

                                        <!-- Rep Price -->
                                        @if(Auth::check() && Auth::user()->is_rep && Auth::user()->representativePackage)
                                        <!-- Rep Price -->

                                        <div class="col-md-1 order-6 order-md-0 text-center">
                                            <a href="#" onclick="openRepPriceModal(this)" data-item-id-price="{{ $cartItem['id'] }}" data-original-price="{{ $cartItem['price'] }}"  data-old-price="{{ $cartItem['rep_price'] }}"  class="btn btn-soft-success btn-icon btn-circle btn-sm" title="{{ translate('Change Item price') }}">
                                                <i class="text-success las la-edit"></i>
                                            </a>
                                        </div>

                                        <div class="col-md-1 order-6 order-md-0 text-center">
                                            <p display_rep_price_id="{{ $cartItem['id'] }}" class="fw-700 fs-14 text-danger">€{{ $cartItem['rep_price'] }}</p>
                                        </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach



                        </ul>
                        <div class="d-flex justify-content-center">
                            {{ $paginator->links() }}
                        </div>
                    </div>

                    <!-- Subtotal -->
                    @if (Auth::check() && Auth::user()->admin_verified)
                        <div class="px-0 py-2 mb-4 border-top d-flex justify-content-between">
                            <span class="opacity-60 fs-14">{{ translate('Subtotal') }}</span>
                            <span class="fw-700 fs-16">{{ single_price($total) }}</span>
                        </div>
                    @endif
                    @if(Auth::check() && Auth::user()->is_rep && Auth::user()->representativePackage)
                        <div class="px-0 py-2 mb-4 border-top d-flex justify-content-between">
                            <span class="opacity-60 fs-14">{{ translate('Edited Subtotal') }}</span>
                            <p id="display-edited-total" class="text-danger fw-700 fs-16">{{ single_price($edited_total) }}</p>
                        </div>
                    @endif
                    @php
                        !isset($offered_cart) ? $offered_cart = collect() : $offered_cart;
                    @endphp
                    @if ($offered_cart->count() != 0 && !$offered_cart->first()->is_postponed)

                        <span class="opacity-60 fs-14">{{ translate('Offerd Products') }}

                        </span>
                        <!-- Cart Items -->
                        <ul class="list-group list-group-flush" id="offer_products">

                            @forelse ($offered_cart as $key => $cartItem)
                                @php
                                    $product = \App\Models\Product::find($cartItem['product_id']);
                                    $product_stock = $product->stocks?->where('variant', $cartItem['variation'])->first();
                                    $total = $total + $cartItem->price;

                                    $product_name_with_choice = $product->getTranslation('name');
                                    if ($cartItem['variation'] != null) {
                                        $product_name_with_choice = $product_name_with_choice . ' - ' . $cartItem['variation'];
                                    }
                                @endphp
                                <li class="list-group-item px-0">
                                    <div class="row gutters-5 align-items-center">

                                        <!-- Product Image & name -->
                                        <div class="col-md-4 d-flex align-items-center mb-2 mb-md-0">
                                            <span class="fs-14">{{ $product_name_with_choice }}</span>
                                        </div>
                                        <!-- Quantity -->
                                        <div class="col-md-1 order-1 order-md-0">
                                            @if ($product->auction_product == 0)
                                                <div
                                                    class="d-flex flex-column align-items-start aiz-plus-minus mr-2 ml-0">

                                                    <input type="number" name="quantity[{{ $cartItem['id'] }}]"
                                                        class="col border-0 text-left px-0 flex-grow-1 fs-14 input-number"
                                                        placeholder="1" value="{{ $cartItem['quantity'] }}"
                                                        min="{{ $product->min_qty }}" max="{{ $product_stock?->qty ?? null }}"
                                                        style="padding-left:0.75rem !important;">
                                                </div>
                                            @elseif($product->auction_product == 1)
                                                <span class="fw-700 fs-14">1</span>
                                            @endif
                                        </div>
                                        @if (Auth::check() && Auth::user()->admin_verified)
                                            <!-- Price -->
                                            <div class="col-md-1 order-2 order-md-0 my-3 my-md-0">
                                                <span
                                                    class="opacity-60 fs-12 d-block d-md-none">{{ translate('Price') }}</span>
                                                <span
                                                    class="fw-700 fs-14">{{ format_price($cartItem->price) }}</span>
                                            </div>
                                            <!-- Total -->
                                            <div class="col-md-1 order-4 order-md-0 my-3 my-md-0">
                                                <span
                                                    class="opacity-60 fs-12 d-block d-md-none">{{ translate('Total') }}</span>
                                                <span
                                                    class="fw-700 fs-16 text-primary">{{ format_price($cartItem->price) }}</span>
                                            </div>
                                        @endif

                                        <!-- Variation -->
                                        <div class="col-md-1 order-5 order-md-0 text-center">
                                            @if (isset($cartResults[$cartItem['id']]))
                                                @foreach ($cartResults[$cartItem['id']] as $key => $cart)
                                                    <div class="fw-400 fs-16">
                                                        {{ $key . ' : ' . $cart }}
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </li>
                                @empty
                                @endforelse
                                <li class="list-group-item px-0">
                                    <div class=" order-5 order-md-0 text-center">
                                        <a href="javascript:void(0)"
                                            onclick="postponed()"
                                            class="btn btn-sm btn-soft-primary bg-soft-warning hov-bg-primary btn-circle">
                                            {{ translate("Do you want to postponed the offer?") }}
                                            <i class="las la-clock fs-16"></i>
                                        </a>

                                        <!-- Remove Offer From Cart -->
                                        <a href="javascript:void(0)"
                                            onclick="removeOffersFromCartView()"
                                            class="btn btn-sm btn-soft-danger hov-bg-danger btn-circle">
                                            {{ translate("Do you want to delete the offer products from your cart?") }}
                                            <i class="las la-trash fs-16"></i>
                                        </a>
                                    </div>
                                </li>
                        </ul>

                        <!-- Subtotal -->
                        @if (Auth::check() && Auth::user()->admin_verified)
                            <div class="px-0 py-2 mb-4 border-top d-flex justify-content-between">
                                <span class="opacity-60 fs-14">{{ translate('Subtotal') }}</span>
                                <span class="fw-700 fs-16">{{ single_price($total) }}</span>
                            </div>
                        @endif
                    @endif

                    <div class="row align-items-center">
                        <!-- Return to shop -->
                        <div class="col-md-6 text-center text-md-left order-1 order-md-0">
                            <a href="{{ route('home') }}" class="btn btn-link fs-14 fw-700 px-0">
                                <i class="las la-arrow-left fs-16"></i>
                                {{ translate('Return to shop') }}
                            </a>
                        </div>
                        <!-- Continue to Shipping -->
                        <div class="col-md-6 text-center text-md-right">
                            @if (Auth::check())
                                <a href="{{ route('checkout.shipping_info') }}"
                                    class="btn btn-primary fs-14 fw-700 rounded-0 px-4">
                                    {{ translate('Continue to Shipping') }}
                                </a>
                            @else
                                <button class="btn btn-primary fs-14 fw-700 rounded-0 px-4"
                                    onclick="showLoginModal()">{{ translate('Continue to Shipping') }}</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-xl-8 mx-auto">
                <div class="border bg-white p-4">
                    <!-- Empty cart -->
                    <div class="text-center p-3">
                        <i class="las la-frown la-3x opacity-60 mb-3"></i>
                        <h3 class="h4 fw-700">{{ translate('Your Cart is empty') }}</h3>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- update notes Modal -->
<div id="update-modal" class="modal fade">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title h6">{{translate('Update Item Notes')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="item-id">
                <textarea id="update-notes" class="form-control" rows="4" value=""></textarea>
                <button type="button" class="btn btn-secondary rounded-0 mt-2" data-dismiss="modal">{{translate('Cancel')}}</button>
                <button type="button" class="btn btn-primary rounded-0 mt-2" onclick="update_notes()">{{translate('Update')}}</button>
            </div>
        </div>
    </div>
</div><!-- /.modal -->


<!-- update price Modal -->
<div id="rep-price-modal" class="modal fade">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title h6">{{translate('Update Item Price')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="item-id-rep">
                <input type="number" id="update-price" class="form-control" rows="4" value=""></input>
                <button type="button" class="btn btn-secondary rounded-0 mt-2" data-dismiss="modal">{{translate('Cancel')}}</button>
                <button type="button" class="btn btn-primary rounded-0 mt-2" onclick="update_price()">{{translate('Update')}}</button>
            </div>
        </div>
    </div>
</div><!-- /.modal -->

<script type="text/javascript">
    AIZ.extra.plusMinus();
</script>

<script>
      recalculateTotal();
    function postponed(){
        $.post('{{ route('cart-offered.postpone') }}', {_token:'{{ csrf_token() }}'}, function(data){
            if(data == 1){
                AIZ.plugins.notify('success', '{{ translate('Offer postponed successfully') }}');
                var offer_products = document.getElementById('offer_products').style.display = 'none';
            }
            else{
                AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
            }
        });
    }
    function removeOffersFromCartView(key){
        $.post('{{ route('cart-offered.removeOffersFromCart') }}', {
            _token  : AIZ.data.csrf,
            id      :  key
        }, function(data){
            updateNavCart(data.nav_cart_view,data.cart_count);
            $('#cart-summary').html(data.cart_view);
            AIZ.plugins.notify('success', "{{ translate('Offer has been removed from cart') }}");
            $('#cart_items_sidenav').html(parseInt($('#cart_items_sidenav').html())-1);
            window.location.reload();
        });
    }

    function update_notes(id){
        var updatedNotes = document.getElementById('update-notes').value;
        var itemId = document.getElementById('item-id').value;
        $('#update-modal').modal('hide');
        $.post('{{ route('cart.update_item_notes') }}', {_token:'{{ csrf_token() }}', id:itemId, item_notes:updatedNotes }, function(data){
            if(data == 1){
                AIZ.plugins.notify('success', '{{ translate('Item Notes updated successfully') }}');
                var updateButton = document.querySelector('[data-item-id="' + itemId + '"]');
                updateButton.setAttribute('data-old-notes', updatedNotes);
            }
            else{
                AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
            }
        });
    }

    function openUpdateModal(button) {
        var itemId = button.getAttribute('data-item-id');
        var oldNotes = button.getAttribute('data-old-notes');
        document.getElementById('update-notes').value = oldNotes;
        document.getElementById('item-id').value = itemId;
        $('#update-modal').modal('show');
    }


    function update_price(id){
        var updatedPrice = document.getElementById('update-price').value;
        updatedPrice = parseFloat(updatedPrice).toFixed(2);
        var itemId = document.getElementById('item-id-rep').value;
        $('#rep-price-modal').modal('hide');
        $.post('{{ route('cart.update_item_price') }}', {_token:'{{ csrf_token() }}', id:itemId, rep_price:updatedPrice }, function(data){
            if(data == 1){
                AIZ.plugins.notify('success', '{{ translate('Item Price updated successfully') }}');
                var updateButton = document.querySelector('[data-item-id-price="' + itemId + '"]');
                updateButton.setAttribute('data-old-price', updatedPrice);
                $('[display_rep_price_id="' + itemId + '"]').text("€" + updatedPrice); // update the <p> tag to display the edited price


                    recalculateTotal();
            }
            else{
                AIZ.plugins.notify('danger', '{{ translate('Price exceeded your discount limit') }}');
            }
        });
    }

    function openRepPriceModal(button) {
        var itemId = button.getAttribute('data-item-id-price');
        var oldRepPrice = button.getAttribute('data-old-price');
        document.getElementById('update-price').value = oldRepPrice;
        document.getElementById('item-id-rep').value = itemId;
        $('#rep-price-modal').modal('show');
    }


    function recalculateTotal() { // thx to chatgpt
    var total = 0;

    // Loop through all cart items to calculate the updated total
    $('[data-item-id-price]').each(function () {
        var itemId = $(this).attr('data-item-id-price');
        var quantity = parseFloat($('[name="quantity[' + itemId + ']"]').val());
        var editedPrice = parseFloat($(this).attr('data-old-price'));

        // Check if there is an edited price and it is not equal to 0
        var price = (!isNaN(editedPrice) && editedPrice !== 0)
            ? editedPrice
            : parseFloat($(this).attr('data-original-price'));

        if (!isNaN(quantity) && !isNaN(price)) {
            total += quantity * price;
        }
    });

    // Update the UI for the total with two decimal places
    $('#display-edited-total').text("€" + total.toFixed(2));
}

</script>
