@php
    $coupon = $coupon ?? null;
    $details = json_decode($coupon?->details ?? '{}', true) ?: [];
    $couponType = $type ?? old('type', $coupon?->type ?? 'cart_base');
    $couponType = in_array($couponType, ['cart_base', 'product_base'], true) ? $couponType : 'cart_base';
    $couponValue = function ($field, $default = '') {
        $value = old($field, $default);
        return is_scalar($value) || $value === null ? $value : '';
    };
    $selected = old('product_ids', array_column($details, 'product_id'));
    $selected = is_array($selected) ? array_filter($selected, 'is_scalar') : [];
    $dates = $coupon ? date('m/d/Y', $coupon->start_date).' - '.date('m/d/Y', $coupon->end_date) : '';
@endphp
<div class="form-group row">
    <label class="col-lg-3 col-form-label" for="code">{{ translate('Coupon code') }}<x-required-mark /></label>
    <div class="col-lg-9">
        <input type="text" id="code" name="code" class="form-control" value="{{ $couponValue('code', $coupon?->code) }}" maxlength="255" required>
        @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
@if ($couponType === 'product_base')
    <div class="form-group row">
        <label class="col-lg-3 col-form-label" for="coupon-products">{{ translate('Product') }}@if($couponType === 'product_base')<x-required-mark />@endif</label>
        <div class="col-lg-9">
            <select id="coupon-products" name="product_ids[]" class="form-control aiz-selectpicker" multiple @required($couponType === 'product_base')>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(in_array((string) $product->id, array_map('strval', $selected), true))>{{ $product->getTranslation('name') }}</option>
                @endforeach
            </select>
            @error('product_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('product_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
@else
    @foreach (['min_buy' => 'Minimum Shopping', 'max_discount' => 'Maximum Discount Amount'] as $field => $label)
        <div class="form-group row">
            <label class="col-lg-3 col-form-label" for="coupon-{{ $field }}">{{ translate($label) }}@if($couponType === 'cart_base')<x-required-mark />@endif</label>
            <div class="col-lg-9">
                <input type="number" id="coupon-{{ $field }}" name="{{ $field }}" class="form-control" min="1" max="999999999999999999.99" step="0.01" value="{{ $couponValue($field, $details[$field] ?? '') }}" @required($couponType === 'cart_base')>
                @error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    @endforeach
@endif
<div class="form-group row">
    <label class="col-lg-3 col-form-label" for="coupon-discount">{{ translate('Discount') }}<x-required-mark /></label>
    <div class="col-lg-9">
        <input type="number" id="coupon-discount" name="discount" class="form-control" min="1" max="999999999999999999.99" step="0.01" value="{{ $couponValue('discount', $coupon?->discount) }}" required>
        @error('discount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label" for="coupon-discount-type">{{ translate('Discount Type') }}<x-required-mark /></label>
    <div class="col-lg-9">
        <select id="coupon-discount-type" name="discount_type" class="form-control aiz-selectpicker" onchange="coupon_discount_limit()" required>
            @foreach (['amount' => 'Amount', 'percent' => 'Percent'] as $value => $label)
                <option value="{{ $value }}" @selected($couponValue('discount_type', $coupon?->discount_type ?? 'amount') === $value)>{{ translate($label) }}</option>
            @endforeach
        </select>
        @error('discount_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label" for="coupon-dates">{{ translate('Date') }}<x-required-mark /></label>
    <div class="col-lg-9">
        <input type="text" id="coupon-dates" name="date_range" class="form-control aiz-date-range" value="{{ $couponValue('date_range', $dates) }}" maxlength="23" required>
        @error('date_range')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
