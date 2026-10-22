@if ($names !== [])
<table class="table table-bordered"><thead><tr><th>{{ translate('Variant') }}</th><th>{{ translate('Variant Price') }}</th><th>{{ translate('SKU') }}</th><th>{{ translate('Quantity') }}</th><th>{{ translate('Photo') }}</th></tr></thead><tbody>
@foreach ($names as $name)
    @php
        $stock = isset($product) ? $product->stocks->firstWhere('variant', $name) : null;
        $key = str_replace(['.', ' '], '_', $name);
        $postedVariants = $postedVariants ?? [];
        $rowValue = function ($prefix, $fallback) use ($key, $postedVariants) {
            $field = $prefix.'_'.$key;
            $candidate = old($field, array_key_exists($field, $postedVariants) ? $postedVariants[$field] : $fallback);
            return is_scalar($candidate) ? $candidate : '';
        };
    @endphp
    <tr class="variant"><td>{{ $name }}</td><td>
        <label for="variant-price-{{ $loop->index }}">{{ translate('Variant Price') }}<x-required-mark /></label>
        <input type="number" id="variant-price-{{ $loop->index }}" name="price_{{ $key }}" value="{{ $rowValue('price', $stock?->price ?? $unit_price) }}" min="0" max="999999999999999999.99" step="0.01" required>
    </td><td><label for="variant-sku-{{ $loop->index }}">{{ translate('SKU') }}</label><input type="text" id="variant-sku-{{ $loop->index }}" name="sku_{{ $key }}" value="{{ $rowValue('sku', $stock?->sku ?? '') }}" maxlength="255"></td><td>
        <label for="variant-qty-{{ $loop->index }}">{{ translate('Quantity') }}<x-required-mark /></label>
        <input type="number" id="variant-qty-{{ $loop->index }}" name="qty_{{ $key }}" value="{{ $rowValue('qty', $stock?->qty ?? 10) }}" min="0" max="2147483647" step="1" required>
    </td><td><label for="variant-img-{{ $loop->index }}">{{ translate('Photo') }}</label><div class="input-group" data-toggle="aizuploader" data-type="image"><div class="input-group-prepend"><div class="input-group-text">{{ translate('Browse') }}</div></div><div class="form-control file-amount">{{ translate('Choose File') }}</div><input type="hidden" name="img_{{ $key }}" id="variant-img-{{ $loop->index }}" value="{{ $rowValue('img', $stock?->image) }}" class="selected-files"></div><div class="file-preview box sm"></div></td></tr>
@endforeach
</tbody></table>
@endif
