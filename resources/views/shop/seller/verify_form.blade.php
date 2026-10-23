@extends('shop.seller.layouts.app')

@section('panel_content')
    @php
        $verificationFields = \App\Support\ShopVerificationForm::fields();
        $verificationErrors = $errors->getBag('sellerVerification');
    @endphp
    <div class="aiz-titlebar mt-2 mb-4">
        <h1 class="h3">{{ translate('Shop Verification') }} <a href="{{ route('shop.visit', $shop->slug) }}" class="btn btn-link btn-sm" target="_blank">({{ translate('Visit Shop') }})<i class="la la-external-link"></i></a></h1>
    </div>
    <form action="{{ route('seller.shop.verify.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card">
            <div class="card-header"><h4 class="mb-0 h6">{{ translate('Verification info') }}</h4></div>
            <div class="card-body">
                @if ($verificationFields)
                    <x-required-legend />
                @endif
                @error('verification_form', 'sellerVerification')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @foreach ($verificationFields as $key => $element)
                    @php
                        $field = 'element_'.$key;
                        $value = $verificationErrors->any() ? old($field) : null;
                        $text = is_scalar($value) ? $value : '';
                        $selected = is_array($value) ? $value : [];
                    @endphp
                    @if ($element->type === 'radio')
                        <fieldset class="form-group">
                            <legend class="h6">{{ $element->label }}<x-required-mark /></legend>
                            @foreach (json_decode($element->options) as $option => $value)
                                <div class="radio radio-inline">
                                    <input type="radio" id="verification-{{ $key }}-{{ $option }}" name="element_{{ $key }}" value="{{ $value }}" @checked($text === $value) required>
                                    <label for="verification-{{ $key }}-{{ $option }}">{{ $value }}</label>
                                </div>
                            @endforeach
                            @error($field, 'sellerVerification')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </fieldset>
                    @else
                        <div class="row form-group">
                            <label class="col-md-2 col-form-label" for="verification-{{ $key }}">{{ $element->label }}<x-required-mark /></label>
                            <div class="col-md-10">
                                @if ($element->type === 'text')
                                    <input type="text" id="verification-{{ $key }}" class="form-control" name="element_{{ $key }}" value="{{ $text }}" maxlength="10000" required>
                                @elseif ($element->type === 'file')
                                    <input type="file" id="verification-{{ $key }}" class="form-control" name="element_{{ $key }}" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" required>
                                @else
                                    <select id="verification-{{ $key }}" class="form-control selectpicker" name="element_{{ $key }}{{ $element->type === 'multi_select' ? '[]' : '' }}" @if($element->type === 'multi_select') multiple @endif required>
                                        @foreach (json_decode($element->options) as $value)
                                            <option value="{{ $value }}" @selected($element->type === 'multi_select' ? in_array($value, $selected, true) : $text === $value)>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @error($field, 'sellerVerification')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @error($field.'.*', 'sellerVerification')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @endif
                @endforeach
                <div class="text-right mt-4"><button type="submit" class="btn btn-primary" @disabled(!$verificationFields)>{{ translate('Apply') }}</button></div>
            </div>
        </div>
    </form>
@endsection
