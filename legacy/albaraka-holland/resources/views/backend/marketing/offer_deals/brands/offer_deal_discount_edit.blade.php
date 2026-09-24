<table class="table table-bordered aiz-table">
    @php
        $brand = \App\Models\Brand::findOrFail($brand);

        $offer_deal_base_product = \App\Models\OfferProduct::where('offer_id', $offer_deal_id)
            ->where('is_base_product', 1)
            ->with('product')
            ->first();
    @endphp
    <thead>
        <tr>
            <td width="50%">
                <span>{{ translate('Brand') }}</span>
            </td>
            <td data-breakpoints="lg">
                <span>{{ translate('Amount Required') }}</span>
            </td>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <div class="from-group row">
                    <div class="col">
                        <span>{{ $brand->name }}</span>
                    </div>
                </div>
            </td>
            <td>
                <input type="number" lang="en" name="brand_amount_required" min="0"
                    value="{{ $offer_deal_base_product?->quantity }}" step="1" class="form-control" required>
            </td>
        </tr>
    </tbody>
</table>


@if (count($product_ids) > 0)
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
                    <span>{{ translate('Discount') }}</span>
                </td>
                <td data-breakpoints="lg" width="20%">
                    <span>{{ translate('Quantity') }}</span>
                </td>
                <td data-breakpoints="lg" width="10%">
                    <span>{{ translate('Discount Type') }}</span>
                </td>
            </tr>
        </thead>
        <tbody>
            @foreach ($product_ids as $key => $id)
                @php
                    $product = \App\Models\Product::findOrFail($id);
                    $offer_deal_product = \App\Models\OfferProduct::where('offer_id', $offer_deal_id)
                        ->where('product_id', $product->id)
                        ->with('product')
                        ->first();
                @endphp
                <tr>
                    <td>
                        <div class="form-group row">
                            <div class="col-auto">
                                <img src="{{ uploaded_asset($product->thumbnail_img) }}" class="size-60px img-fit">
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
                        <input type="number" lang="en" name="discount_{{ $id }}"
                            value="{{ $offer_deal_product?->discount }}" min="0" step="1" class="form-control"
                            required>
                    </td>
                    <td>
                        <input type="number" lang="en" name="discount_quantity_{{ $id }}"
                            value="{{ $offer_deal_product?->quantity }}" min="0" step="1" class="form-control"
                            required>
                    </td>
                    <td>
                        <select class="aiz-selectpicker" name="discount_type_{{ $id }}">
                            <option value="amount" <?php if ($offer_deal_product?->discount_type == 'amount') {
                                echo 'selected';
                            } ?>>{{ translate('Flat') }}</option>
                            <option value="percent" <?php if ($offer_deal_product?->discount_type == 'percent') {
                                echo 'selected';
                            } ?>>{{ translate('Percent') }}</option>
                        </select>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
