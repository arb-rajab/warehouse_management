@php
    $editing = isset($product);
    $value = function (string $key, mixed $fallback = '') use ($editing, $product, $lang) {
        if (session()->hasOldInput()) {
            $input = old($key, $fallback);
        } elseif ($editing) {
            $input = match ($key) {
                'name', 'description' => $product->getTranslation($key, $lang),
                'tags.0' => json_encode(array_map(fn ($tag) => ['value' => $tag], array_filter(explode(',', (string) $product->tags)))),
                'file' => $product->file_name,
                default => $product->getAttribute($key) ?? $fallback,
            };
        } else {
            $input = $fallback;
        }
        if (! session()->hasOldInput() && in_array($key, ['unit_price', 'purchase_price', 'discount'], true)) {
            $input = \App\Support\ProductFormNumber::format($input);
        }
        return is_scalar($input) ? $input : $fallback;
    };
@endphp
<x-required-legend />
@if ($errors->any())
    <div class="alert alert-danger" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@if ($editing)
    <input type="hidden" name="lang" value="{{ $lang }}">
    <nav class="mb-3">@foreach ($languages as $language)<a class="btn btn-link" href="{{ route('seller.digitalproducts.edit', ['id' => $product->id, 'lang' => $language->code]) }}">{{ $language->name }}</a>@endforeach</nav>
@endif
<div class="card"><div class="card-header"><h5>{{ __('storefront.common.general') }}</h5></div><div class="card-body">
    <div class="form-group"><label for="digital-name">{{ __('storefront.product.product_name') }}<x-required-mark /></label>
        <input type="text" id="digital-name" name="name" value="{{ $value('name') }}" class="form-control" maxlength="200" required>
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="form-group"><label for="digital-category">{{ __('storefront.product.category') }}<x-required-mark /></label>
        <select id="digital-category" name="category_id" class="form-control aiz-selectpicker" required>
            <option value="">{{ __('seller.products.select_category') }}</option>
            @foreach ($categoryOptions as $category)<option value="{{ $category['id'] }}" @selected((string) $value('category_id') === (string) $category['id'])>{{ $category['name'] }}</option>@endforeach
        </select>
        @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="form-group"><label id="digital-file-label" for="digital-file">{{ __('seller.products.product_file') }}<x-required-mark /></label>
        <div class="input-group" data-toggle="aizuploader" data-multiple="false" role="group" tabindex="0" aria-required="true" aria-labelledby="digital-file-label">
            <div class="input-group-prepend"><div class="input-group-text bg-soft-secondary font-weight-medium">{{ __('storefront.upload.browse') }}</div></div>
            <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
            <input type="hidden" id="digital-file" name="file" class="selected-files" value="{{ $value('file') }}" required>
        </div><div class="file-preview box sm"></div>
        <div class="invalid-feedback d-block" role="alert" data-digital-file-error data-message="{{ __('seller.products.please_choose_a_product_file') }}" hidden></div>
        @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="form-group"><label for="digital-tags">{{ __('seller.products.tags') }}</label>
        <input type="text" id="digital-tags" name="tags[]" class="form-control aiz-tag-input" value="{{ $value('tags.0') }}">
        @error('tags.0')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div></div>
<div class="card"><div class="card-header"><h5>{{ __('storefront.common.images') }}</h5></div><div class="card-body">
    <div class="form-group"><label for="digital-photos">{{ __('storefront.common.gallery_images') }}</label>
        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
            <div class="input-group-prepend"><div class="input-group-text bg-soft-secondary font-weight-medium">{{ __('storefront.upload.browse') }}</div></div><div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
            <input type="hidden" name="photos" id="digital-photos" class="selected-files" value="{{ $value('photos') }}">
        </div><div class="file-preview box sm"></div>
        @error('photos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="form-group"><label for="digital-thumbnail">{{ __('storefront.common.thumbnail_image') }}</label>
        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="false">
            <div class="input-group-prepend"><div class="input-group-text bg-soft-secondary font-weight-medium">{{ __('storefront.upload.browse') }}</div></div><div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
            <input type="hidden" name="thumbnail_img" id="digital-thumbnail" class="selected-files" value="{{ $value('thumbnail_img') }}">
        </div><div class="file-preview box sm"></div>
        @error('thumbnail_img')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div></div>
<div class="card"><div class="card-header"><h5>{{ __('storefront.common.meta_tags') }}</h5></div><div class="card-body">
    <div class="form-group"><label for="digital-meta-title">{{ __('storefront.common.meta_title') }}</label><input type="text" id="digital-meta-title" name="meta_title" class="form-control" value="{{ $value('meta_title') }}"></div>
    <div class="form-group"><label for="digital-meta-description">{{ __('seller.products.meta_description') }}</label><textarea id="digital-meta-description" name="meta_description" class="form-control" rows="5">{{ $value('meta_description') }}</textarea></div>
    <div class="form-group"><label for="digital-meta-image">{{ __('storefront.common.meta_image') }}</label>
        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="false">
            <div class="input-group-prepend"><div class="input-group-text bg-soft-secondary font-weight-medium">{{ __('storefront.upload.browse') }}</div></div><div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
            <input type="hidden" name="meta_img" id="digital-meta-image" class="selected-files" value="{{ $value('meta_img') }}">
        </div><div class="file-preview box sm"></div>
        @error('meta_img')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    @if ($editing)
        <div class="form-group"><label for="digital-slug">{{ __('storefront.common.slug') }}</label><input type="text" id="digital-slug" name="slug" class="form-control" maxlength="255" pattern="[a-zA-Z0-9\-]+" value="{{ $value('slug') }}">
        @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
    @endif
</div></div>
<div class="card"><div class="card-header"><h5>{{ __('storefront.product.price') }}</h5></div><div class="card-body">
    <div class="form-group"><label for="digital-unit-price">{{ __('storefront.product.unit_price') }}<x-required-mark /></label>
        <input type="number" id="digital-unit-price" name="unit_price" class="form-control" value="{{ $value('unit_price', 0) }}" min="0" max="999999999999999999.99" step="0.01" required>
        @error('unit_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="form-group"><label for="digital-purchase-price">{{ __('seller.products.purchase_price') }}<x-required-mark /></label>
        <input type="number" id="digital-purchase-price" name="purchase_price" class="form-control" value="{{ $value('purchase_price', 0) }}" min="0" max="999999999999999999.99" step="0.01" required>
        @error('purchase_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    @foreach ($taxes as $tax)
        @php
            $storedTax = $editing ? $product->taxes->firstWhere('tax_id', $tax->id) : null;
            $taxAmount = session()->hasOldInput() ? old('tax.'.$loop->index, 0) : ($storedTax?->tax ?? 0);
            $taxType = session()->hasOldInput() ? old('tax_type.'.$loop->index, 'amount') : ($storedTax?->tax_type ?? 'amount');
        @endphp
        <div class="form-group"><label for="digital-tax-{{ $tax->id }}">{{ $tax->name }}<x-required-mark /></label>
            <input type="hidden" name="tax_id[]" value="{{ $tax->id }}">
            <input type="number" id="digital-tax-{{ $tax->id }}" name="tax[]" class="form-control" min="0" max="999999999999999999.99" step="0.01" value="{{ is_scalar($taxAmount) ? (session()->hasOldInput() ? $taxAmount : \App\Support\ProductFormNumber::format($taxAmount)) : 0 }}" required>
            <label for="digital-tax-type-{{ $tax->id }}">{{ __('seller.products.tax_type') }}<x-required-mark /></label>
            <select id="digital-tax-type-{{ $tax->id }}" name="tax_type[]" class="form-control aiz-selectpicker" required><option value="amount" @selected($taxType === 'amount')>{{ __('seller.products.flat') }}</option><option value="percent" @selected($taxType === 'percent')>{{ __('seller.products.percent') }}</option></select>
        </div>
    @endforeach
    <div class="form-group"><label for="digital-discount">{{ __('seller.coupons.discount') }}<x-required-mark /></label>
        <input type="number" id="digital-discount" name="discount" class="form-control" value="{{ $value('discount', 0) }}" min="0" max="999999999999999999.99" step="0.01" required>
        @error('discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="form-group"><label for="digital-discount-type">{{ __('seller.coupons.discount_type') }}<x-required-mark /></label>
        <select id="digital-discount-type" name="discount_type" class="form-control aiz-selectpicker" required><option value="amount" @selected($value('discount_type', 'amount') === 'amount')>{{ __('seller.products.flat') }}</option><option value="percent" @selected($value('discount_type') === 'percent')>{{ __('seller.products.percent') }}</option></select>
        @error('discount_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div></div>
<div class="card"><div class="card-header"><h5>{{ __('seller.products.product_information') }}</h5></div><div class="card-body">
    <label for="digital-description">{{ __('storefront.common.description') }}</label><textarea id="digital-description" class="aiz-text-editor" name="description">{{ $value('description') }}</textarea>
    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div></div>
