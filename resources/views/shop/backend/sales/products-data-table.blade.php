@if (count($product_ids) > 0)
    <table class="table table-bordered aiz-table">
        <thead>
            <tr>
                <td width="40%"><span>{{ translate('Product') }}</span></td>
                <td width="20%"><span>{{ translate('Price') }}</span></td>
                <td width="20%"><span>{{ translate('Edited Price') }}</span></td>
                <td width="25%"><span>{{ translate('Quantity') }}</span></td>
                <td width="10%"><span>{{ translate('Stock') }}</span></td>
                <td width="20%"><span>{{ translate('Notes') }}</span></td>
            </tr>
        </thead>
        <tbody>
            @foreach ($product_ids as $key => $id)
                @php
                    $product = \App\Models\Product::findOrFail($id);
                    $variations = \App\Models\ProductVariation::where('product_id', $product->id)->get();
                    $stocks = \App\Models\ProductStock::where('product_id', $product->id)->get();
                @endphp
                <tr>
                    <td>
                        <div class="from-group row">
                            <div class="col-auto">
                                <img class="size-60px img-fit" src="{{ uploaded_asset($product->thumbnail_img) }}">
                            </div>
                            <div class="col">
                                <span>{{ $product->getTranslation('name') }}</span>
                            </div>
                        </div>
                    </td>
                    <td><span>{{ $product->unit_price }}</span></td>
                    <td>
                        <input type="number" lang="en" name="edited_price_{{ $id }}" min="0"
                            step="0.1" class="form-control">
                    </td>
                    <td>
                        @if ($variations->count() > 0)
                            @foreach ($variations as $variation)
                                <div class="row no-gutters mb-3">
                                    <div class="col-sm-2">
                                        <div class="text-secondary fs-14 fw-400 mt-2">{{ $variation->name }}</div>
                                    </div>
                                    <div class="col-sm-10">
                                        <div class="d-flex align-items-center">
                                            <div class="row no-gutters align-items-center mr-3" style="width: 130px;">
                                                <button class="btn col-auto btn-icon btn-sm btn-light rounded-0"
                                                    type="button" data-type="minuss">
                                                    <i class="las la-minus"></i>
                                                </button>
                                                <input type="number"
                                                    name="variation_qty_{{ $id }}[{{ $variation->name }}]"
                                                    class="col border-0 text-center flex-grow-1 fs-16 input-number"
                                                    placeholder="1" lang="en" value="0" min="0">
                                                <button class="btn col-auto btn-icon btn-sm btn-light rounded-0"
                                                    type="button" data-type="pluss">
                                                    <i class="las la-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <input type="number" lang="en" name="quantity_{{ $id }}" min="0"
                                step="1" class="form-control" required>
                        @endif
                    </td>
                    <td>
                        @if ($stocks->count() > 0)
                            @foreach ($stocks as $stock)
                                <div class="row no-gutters mb-3">
                                    <div class="col-sm-2">
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input"
                                                name="variations_{{ $id }}"
                                                id="variation_{{ $stock->id }}" value="{{ $stock->variant }}"
                                                {{ $stock->variant == 'PerBox' ?? 'selected' }}>
                                            <label class="form-check-label text-secondary fs-14 fw-400"
                                                for="variation_{{ $stock->id }}">{{ $stock->variant }}</label>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <input type="number" lang="en" name="quantity_{{ $id }}" min="0"
                                step="1" class="form-control" required>
                        @endif
                    </td>
                    <td>
                        <textarea type="text" placeholder="{{ translate('Notes') }}" id="item_notes_{{ $id }}"
                            name="item_notes_{{ $id }}" class="form-control"></textarea>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<script>
    document.addEventListener("DOMContentLoaded", function() {
        function handleQuantityChange(event, increment) {
            const inputField = event.target.closest('.row').querySelector('.input-number');
            if (inputField) {
                const newValue = Math.max(parseInt(inputField.value, 10) + increment, 0);
                inputField.value = newValue;
                updateTotalQuantity(inputField);
            }
        }

        function updateTotalQuantity(inputField) {
            const row = inputField.closest('.row');
            if (!row) return;

            const productIdMatch = inputField.name.match(/variation_qty_(\d+)/);
            if (!productIdMatch) return;

            const productId = productIdMatch[1];
            let total = 0;

            document.querySelectorAll(`[name^="variation_qty_${productId}"]`).forEach(input => {
                total += parseInt(input.value, 10) || 0;
            });

            const totalQuantityInput = document.querySelector(`[name="quantity_${productId}"]`);
            if (totalQuantityInput) {
                totalQuantityInput.value = total;
            }
        }

        document.addEventListener('click', function(event) {
            const target = event.target;
            if (target.matches('[data-type="pluss"], [data-type="minuss"]')) {
                const inputField = target.closest('.row').querySelector('.input-number');
                const increment = target.dataset.type === 'pluss' ? 1 : -1;
                if (inputField) {
                    inputField.value = Math.max(parseInt(inputField.value, 10) + increment, 0);
                    updateTotalQuantity(inputField);
                }
            }
        });

        document.querySelectorAll('[name^="variation_qty_"]').forEach(input => {
            input.addEventListener("input", function() {
                updateTotalQuantity(this);
            });
        });
    });
</script>
