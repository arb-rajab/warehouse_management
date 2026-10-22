@php
    $editing = isset($product);
    $stored = $editing ? $product->getAttributes() : [];
    $firstStock = $editing ? $product->stocks->first() : null;
    $defaults = array_replace($stored, [
        'name' => $editing ? $product->getTranslation('name', $lang) : '',
        'unit' => $editing ? $product->getTranslation('unit', $lang) : '',
        'description' => $editing ? $product->getTranslation('description', $lang) : '',
        'current_stock' => $firstStock?->qty ?? 0, 'sku' => $firstStock?->sku ?? '',
        'flat_shipping_cost' => $stored['shipping_cost'] ?? 0,
        'date_range' => !empty($stored['discount_start_date']) && !empty($stored['discount_end_date']) ? date('d-m-Y H:i:s', $stored['discount_start_date']).' to '.date('d-m-Y H:i:s', $stored['discount_end_date']) : '',
        'tags.0' => $stored['tags'] ?? '',
    ]);
    $value = function ($key, $fallback = '') use ($defaults) {
        $candidate = session()->hasOldInput() ? old($key, '') : ($defaults[$key] ?? $fallback);
        return is_scalar($candidate) ? $candidate : '';
    };
    $arrayValue = function ($key, $fallback = []) {
        $candidate = session()->hasOldInput() ? old($key, []) : $fallback;
        return is_array($candidate) ? array_values(array_filter($candidate, 'is_scalar')) : [];
    };
    $storedColors = json_decode($stored['colors'] ?? '[]', true);
    $chosenColors = $arrayValue('colors', is_array($storedColors) ? $storedColors : []);
    $storedChoices = json_decode($stored['choice_options'] ?? '[]', true);
    $storedChoices = is_array($storedChoices) ? $storedChoices : [];
    $choiceIds = $arrayValue('choice_no', array_column($storedChoices, 'attribute_id'));
    $choiceIds = array_slice(array_values(array_filter($choiceIds, fn ($id) => ctype_digit((string) $id))), 0, 20);
    $choiceValues = [];
    foreach ($choiceIds as $choiceId) {
        $storedChoice = collect($storedChoices)->first(fn ($choice) => is_array($choice) && ($choice['attribute_id'] ?? null) == $choiceId);
        $choiceValues[$choiceId] = $arrayValue('choice_options_'.$choiceId, $storedChoice['values'] ?? []);
    }
    $colorsOn = session()->hasOldInput() ? (string) $value('colors_active') === '1' : $chosenColors !== [];
    $names = array_product(array_map('count', [$colorsOn ? ($chosenColors ?: [null]) : [null], ...array_values($choiceValues)])) <= 500
        ? app(\App\Services\Store\ProductVariantCombinations::class)->names($colorsOn ? $chosenColors : [], array_values($choiceValues)) : [];
@endphp
@if ($editing)
    <input type="hidden" name="lang" value="{{ $lang }}">
    <nav class="mb-3">@foreach ($languages as $language)<a class="btn btn-link" href="{{ route('seller.products.edit', ['id' => $product->id, 'lang' => $language->code]) }}">{{ $language->name }}</a>@endforeach</nav>
@endif
<div class="form-group"><label for="seller-product-name">{{ translate('Product Name') }}<x-required-mark /></label>
    <input type="text" name="name" id="seller-product-name" class="form-control" value="{{ $value('name') }}" required aria-required="true" maxlength="200">
    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-category_id">{{ translate('Category') }}<x-required-mark /></label>
<select name="category_id" id="seller-product-category_id" class="form-control aiz-selectpicker" required aria-required="true">
<option value="">{{ translate('Select category') }}</option>@foreach ($categoryOptions as $category)<option value="{{ $category['id'] }}" @selected((string) $value('category_id') === (string) $category['id'])>{{ $category['name'] }}</option>@endforeach
</select>
@error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="form-group"><label for="seller-product-brand_id">{{ translate('Brand') }}</label>
<select name="brand_id" id="seller-product-brand_id" class="form-control aiz-selectpicker">
<option value="">{{ translate('Select Brand') }}</option>@foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected((string) $value('brand_id') === (string) $brand->id)>{{ $brand->getTranslation('name') }}</option>@endforeach
</select>
@error('brand_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="form-group"><label for="seller-product-unit">{{ translate('Unit') }}<x-required-mark /></label>
    <input type="text" name="unit" id="seller-product-unit" class="form-control" value="{{ $value('unit') }}" required aria-required="true" maxlength="20">
    @error('unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-weight">{{ translate('Weight') }}</label>
    <input type="number" name="weight" id="seller-product-weight" class="form-control" value="{{ $value('weight', 0) }}" min="0" max="999999.99" step="0.01">
    @error('weight')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-min_qty">{{ translate('Minimum Purchase Qty') }}<x-required-mark /></label>
    <input type="number" name="min_qty" id="seller-product-min_qty" class="form-control" value="{{ $value('min_qty', 1) }}" required aria-required="true" min="1" max="2147483647" step="1">
    @error('min_qty')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-tags">{{ translate('Tags') }}</label><input type="text" name="tags[]" id="seller-product-tags" class="form-control aiz-tag-input" value="{{ $value('tags.0') }}"></div>
@if (addon_is_activated('pos_system'))
<div class="form-group"><label for="seller-product-barcode">{{ translate('Barcode') }}</label>
    <input type="text" name="barcode" id="seller-product-barcode" class="form-control" value="{{ $value('barcode') }}" maxlength="255">
    @error('barcode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
@endif
<div class="form-group"><label for="seller-product-photos">{{ translate('Gallery Images') }}</label><div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true"><div class="input-group-prepend"><div class="input-group-text">{{ translate('Browse') }}</div></div><div class="form-control file-amount">{{ translate('Choose File') }}</div><input type="hidden" name="photos" id="seller-product-photos" class="selected-files" value="{{ $value('photos') }}"></div><div class="file-preview box sm"></div></div>
<div class="form-group"><label for="seller-product-thumbnail_img">{{ translate('Thumbnail Image') }}</label><div class="input-group" data-toggle="aizuploader" data-type="image" ><div class="input-group-prepend"><div class="input-group-text">{{ translate('Browse') }}</div></div><div class="form-control file-amount">{{ translate('Choose File') }}</div><input type="hidden" name="thumbnail_img" id="seller-product-thumbnail_img" class="selected-files" value="{{ $value('thumbnail_img') }}"></div><div class="file-preview box sm"></div></div>
<div class="form-group"><label for="seller-product-pdf">{{ translate('PDF Specification') }}</label><div class="input-group" data-toggle="aizuploader" data-type="document" ><div class="input-group-prepend"><div class="input-group-text">{{ translate('Browse') }}</div></div><div class="form-control file-amount">{{ translate('Choose File') }}</div><input type="hidden" name="pdf" id="seller-product-pdf" class="selected-files" value="{{ $value('pdf') }}"></div><div class="file-preview box sm"></div></div>
<div class="form-group"><label for="seller-product-meta_img">{{ translate('Meta Image') }}</label><div class="input-group" data-toggle="aizuploader" data-type="image" ><div class="input-group-prepend"><div class="input-group-text">{{ translate('Browse') }}</div></div><div class="form-control file-amount">{{ translate('Choose File') }}</div><input type="hidden" name="meta_img" id="seller-product-meta_img" class="selected-files" value="{{ $value('meta_img') }}"></div><div class="file-preview box sm"></div></div>
<div class="form-group"><label for="seller-product-video_provider">{{ translate('Video Provider') }}</label>
<select name="video_provider" id="seller-product-video_provider" class="form-control aiz-selectpicker">
<option value="youtube" @selected($value('video_provider', 'youtube') === 'youtube')>Youtube</option><option value="dailymotion" @selected($value('video_provider', 'youtube') === 'dailymotion')>Dailymotion</option><option value="vimeo" @selected($value('video_provider', 'youtube') === 'vimeo')>Vimeo</option>
</select>
@error('video_provider')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="form-group"><label for="seller-product-video_link">{{ translate('Video Link') }}</label>
    <input type="url" name="video_link" id="seller-product-video_link" class="form-control" value="{{ $value('video_link') }}" maxlength="100">
    @error('video_link')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<fieldset class="form-group"><legend>{{ translate('Product Variation') }}</legend>
<label for="colors">{{ translate('Colors') }}</label><select name="colors[]" id="colors" class="form-control aiz-selectpicker" multiple @disabled(!$colorsOn)>@foreach ($colors as $color)<option value="{{ $color->code }}" @selected(in_array($color->code, $chosenColors, true))>{{ $color->name }}</option>@endforeach</select>
<label><input type="checkbox" name="colors_active" value="1" @checked($colorsOn)>{{ translate('Enable colors') }}</label>
<label for="choice_attributes">{{ translate('Attributes') }}</label><select name="choice_attributes[]" id="choice_attributes" class="form-control aiz-selectpicker" multiple>@foreach ($attributes as $attribute)<option value="{{ $attribute->id }}" @selected(in_array((string) $attribute->id, array_map('strval', $choiceIds), true))>{{ $attribute->getTranslation('name') }}</option>@endforeach</select>
<div id="customer_choice_options">@foreach ($choiceIds as $choiceId)
    @php($attribute = $attributes->firstWhere('id', $choiceId))
    @if ($attribute)
    <div class="form-group row"><div class="col-md-3"><input type="hidden" name="choice_no[]" value="{{ $choiceId }}"><label for="choice-options-{{ $choiceId }}">{{ $attribute->getTranslation('name') }}<x-required-mark /></label></div>
    <div class="col-md-8"><select name="choice_options_{{ $choiceId }}[]" id="choice-options-{{ $choiceId }}" class="form-control aiz-selectpicker attribute_choice" multiple required aria-required="true">@foreach ($attribute->attribute_values as $option)<option value="{{ $option->value }}" @selected(in_array($option->value, $choiceValues[$choiceId], true))>{{ $option->value }}</option>@endforeach</select></div></div>
    @endif
@endforeach</div>
</fieldset>
<div class="form-group"><label for="seller-product-unit_price">{{ translate('Unit price') }}<x-required-mark /></label>
    <input type="number" name="unit_price" id="seller-product-unit_price" class="form-control" value="{{ $value('unit_price', 0) }}" required aria-required="true" min="0" max="999999999999999999.99" step="0.01">
    @error('unit_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-date_range">{{ translate('Discount Date Range') }}</label>
    <input type="text" name="date_range" id="seller-product-date_range" class="form-control aiz-date-range" value="{{ $value('date_range') }}" data-time-picker="true" data-format="DD-MM-Y HH:mm:ss" data-separator=" to " autocomplete="off">
    @error('date_range')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-discount">{{ translate('Discount') }}<x-required-mark /></label>
    <input type="number" name="discount" id="seller-product-discount" class="form-control" value="{{ $value('discount', 0) }}" required aria-required="true" min="0" max="999999999999999999.99" step="0.01">
    @error('discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-discount_type">{{ translate('Discount type') }}<x-required-mark /></label>
<select name="discount_type" id="seller-product-discount_type" class="form-control aiz-selectpicker" required aria-required="true">
<option value="amount" @selected($value('discount_type', 'amount') === 'amount')>{{ translate('Flat') }}</option><option value="percent" @selected($value('discount_type') === 'percent')>{{ translate('Percent') }}</option>
</select>
@error('discount_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div id="show-hide-div">
<div class="form-group"><label for="seller-product-current_stock">{{ translate('Quantity') }}<x-required-mark /></label>
    <input type="number" name="current_stock" id="seller-product-current_stock" class="form-control" value="{{ $value('current_stock', 0) }}" required aria-required="true" min="0" max="2147483647" step="1">
    @error('current_stock')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-sku">{{ translate('SKU') }}</label>
    <input type="text" name="sku" id="seller-product-sku" class="form-control" value="{{ $value('sku') }}" maxlength="255">
    @error('sku')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
</div>
<div id="sku_combination">@include('shop.seller.product.products.variant_rows', ['unit_price' => $value('unit_price', 0)])</div>
<div class="form-group"><label for="seller-product-external_link">{{ translate('External link') }}</label>
    <input type="url" name="external_link" id="seller-product-external_link" class="form-control" value="{{ $value('external_link') }}" maxlength="500">
    @error('external_link')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-external_link_btn">{{ translate('External link button text') }}</label>
    <input type="text" name="external_link_btn" id="seller-product-external_link_btn" class="form-control" value="{{ $value('external_link_btn') }}" maxlength="255">
    @error('external_link_btn')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-description">{{ translate('Description') }}</label>
    <textarea name="description" id="seller-product-description" class="form-control aiz-text-editor">{{ $value('description') }}</textarea>
    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-meta_title">{{ translate('Meta Title') }}</label>
    <input type="text" name="meta_title" id="seller-product-meta_title" class="form-control" value="{{ $value('meta_title') }}" >
    @error('meta_title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-meta_description">{{ translate('Meta Description') }}</label>
    <textarea name="meta_description" id="seller-product-meta_description" class="form-control" >{{ $value('meta_description') }}</textarea>
    @error('meta_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
@if (get_setting('Setup_Shipping_section') == 1)
@if (get_setting('shipping_type') === 'product_wise_shipping')
<div class="form-group"><label for="seller-product-shipping_type">{{ translate('Shipping type') }}</label>
<select name="shipping_type" id="seller-product-shipping_type" class="form-control aiz-selectpicker">
<option value="free" @selected($value('shipping_type', 'free') === 'free')>{{ translate('Free Shipping') }}</option><option value="flat_rate" @selected($value('shipping_type') === 'flat_rate')>{{ translate('Flat Rate') }}</option>
</select>
@error('shipping_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="form-group"><label for="seller-product-flat_shipping_cost">{{ translate('Shipping cost') }}@if($value('shipping_type', 'free') === 'flat_rate')<span data-shipping-required><x-required-mark /></span>@endif</label>
    <input type="number" name="flat_shipping_cost" id="seller-product-flat_shipping_cost" @required($value('shipping_type', 'free') === 'flat_rate') class="form-control" value="{{ $value('flat_shipping_cost', 0) }}" min="0" max="999999999999999999.99" step="0.01">
    @error('flat_shipping_cost')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
@endif
<div class="form-group"><label for="seller-product-est_shipping_days">{{ translate('Shipping Days') }}</label>
    <input type="number" name="est_shipping_days" id="seller-product-est_shipping_days" class="form-control" value="{{ $value('est_shipping_days') }}" min="1" max="2147483647" step="1">
    @error('est_shipping_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
@endif
<div class="form-group"><label for="seller-product-low_stock_quantity">{{ translate('Low Stock Quantity Warning') }}</label>
    <input type="number" name="low_stock_quantity" id="seller-product-low_stock_quantity" class="form-control" value="{{ $value('low_stock_quantity', 1) }}" min="0" max="2147483647" step="1">
    @error('low_stock_quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="form-group"><label for="seller-product-stock_visibility_state">{{ translate('Stock Visibility State') }}<x-required-mark /></label>
<select name="stock_visibility_state" id="seller-product-stock_visibility_state" class="form-control aiz-selectpicker" required aria-required="true">
<option value="quantity" @selected($value('stock_visibility_state', 'quantity') === 'quantity')>{{ translate('Show Stock Quantity') }}</option><option value="text" @selected($value('stock_visibility_state', 'quantity') === 'text')>{{ translate('Show Stock With Text Only') }}</option><option value="hide" @selected($value('stock_visibility_state', 'quantity') === 'hide')>{{ translate('Hide Stock') }}</option>
</select>
@error('stock_visibility_state')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<input type="hidden" name="cash_on_delivery" value="1">
@if ($editing)<div class="form-group"><label for="seller-product-slug">{{ translate('Slug') }}</label><input type="text" name="slug" id="seller-product-slug" value="{{ $value('slug') }}" maxlength="255" class="form-control"></div>@endif
@if (get_setting('VAT_TAX_section') == 1)
<fieldset><legend>{{ translate('VAT & Tax') }}</legend>
@foreach ($taxes as $index => $tax)
    @php($storedTax = $editing ? $product->taxes->firstWhere('tax_id', $tax->id) : null)
    <input type="hidden" name="tax_id[]" value="{{ $tax->id }}">
    <label for="seller-tax-{{ $tax->id }}">{{ $tax->name }}<x-required-mark /></label>
    <input type="number" id="seller-tax-{{ $tax->id }}" name="tax[]" value="{{ $value('tax.'.$index, $storedTax?->tax ?? 0) }}" min="0" max="999999999999999999.99" step="0.01" required>
    <label for="seller-tax-type-{{ $tax->id }}">{{ translate('Tax type') }}<x-required-mark /></label>
    <select id="seller-tax-type-{{ $tax->id }}" name="tax_type[]" required><option value="amount" @selected($value('tax_type.'.$index, $storedTax?->tax_type ?? 'amount') === 'amount')>{{ translate('Flat') }}</option><option value="percent" @selected($value('tax_type.'.$index, $storedTax?->tax_type ?? 'amount') === 'percent')>{{ translate('Percent') }}</option></select>
@endforeach</fieldset>
@endif
@if ($editing && $unmatchedStocks->isNotEmpty())
<div class="alert alert-warning" role="alert">{{ translate('Stored stock does not match the product choices. Reconcile the choices with the existing stock before saving.') }}
<ul>@foreach ($unmatchedStocks as $stock)<li>{{ $stock->variant }} — {{ $stock->price }} — {{ $stock->qty }} — {{ $stock->sku }}</li>@endforeach</ul></div>
@endif
