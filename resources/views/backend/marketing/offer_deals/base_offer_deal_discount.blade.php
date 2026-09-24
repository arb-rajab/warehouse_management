<table class="table table-bordered aiz-table">
    <thead>
        <tr>
            <td width="50%">
                <span>{{ translate('Product') }}</span>
            </td>
            <td data-breakpoints="lg" width="20%">
                <span>{{ translate('Base Price') }}</span>
            </td>
            <td data-breakpoints="lg" width="20%">
                <span>{{ translate('Quantity Required') }}</span>
            </td>
        </tr>
    </thead>
    <tbody>
        @php
            $product = \App\Models\Product::findOrFail($product_id);
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
            <td>
                <span>{{ $product->unit_price }}</span>
            </td>
            <td>
                <input type="number" lang="en" name="base_quantity_required" min="0"
                    step="1" class="form-control" required>
            </td>
        </tr>
    </tbody>
</table>
