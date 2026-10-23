{{--
    The country row of the address forms (storefront and seller panel), for
    App\Http\Requests\Concerns\ValidatesAddress: `country_id` is required, and
    marked, while the store has an active country to choose. With none set up
    (a fresh database), the row says so and the form saves without a country.

        <x-address-country-field id="new_address_country" bag="storeAddress" :selected="$address->country_id" />
--}}
@props(['id', 'bag' => 'default', 'selected' => null])
@php
    $countries = \App\Models\Country::active()->orderByDesc('priority')->orderBy('name')->get(['id', 'name']);
    $error = $errors->getBag($bag)->first('country_id');
    $chosen = $errors->getBag($bag)->any() ? old('country_id') : $selected;
@endphp
<div class="row">
    <div class="col-md-2">
        <label for="{{ $id }}">{{ translate('Country') }}@if ($countries->isNotEmpty())<x-required-mark />@endif</label>
    </div>
    <div class="col-md-10">
        <div class="mb-3">
            @if ($countries->isNotEmpty())
                <select id="{{ $id }}" class="form-control aiz-selectpicker{{ $error ? ' is-invalid' : '' }}" data-live-search="true" data-placeholder="{{ translate('Select your country') }}" name="country_id" required>
                    <option value="">{{ translate('Select your country') }}</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected((string) $country->id === (string) $chosen)>{{ $country->name }}</option>
                    @endforeach
                </select>
            @else
                <p id="{{ $id }}" class="fs-13 text-muted mb-0" data-no-countries>{{ translate('No countries are set up yet. You can save the address without one.') }}</p>
            @endif
            @if ($error)
                <span class="invalid-feedback d-block" role="alert"><strong>{{ $error }}</strong></span>
            @endif
        </div>
    </div>
</div>
