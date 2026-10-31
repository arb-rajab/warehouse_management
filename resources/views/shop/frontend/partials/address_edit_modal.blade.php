<form class="form-default" role="form" action="{{ route('addresses.update', $address_data->id) }}" method="POST">
    @csrf
    @php
        $keepInput = $errors->updateAddress->any();
    @endphp
    {{-- Sent back with the input, so a failed update can reopen this address's form. --}}
    <input type="hidden" name="address_id" value="{{ $address_data->id }}">
    <div class="p-3">
        <x-required-legend />
        <!-- Address -->
        <div class="row">
            <div class="col-md-2">
                <label for="edit_address_address">{{ __('storefront.common.address')}}<x-required-mark /></label>
            </div>
            <div class="col-md-10 mb-3">
                <textarea id="edit_address_address" class="form-control rounded-0 @error('address', 'updateAddress') is-invalid @enderror" placeholder="{{ __('storefront.checkout.shipping_address')}}" rows="2" name="address" maxlength="255" required>{{ $keepInput ? old('address') : $address_data->address }}</textarea>
                @error('address', 'updateAddress')
                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>

        <!-- Country -->
        <x-address-country-field id="edit_country" bag="updateAddress" :selected="$address_data->country_id" />

        @if (get_setting('google_map') == 1)
            <!-- Google Map -->
            <div class="row mt-3 mb-3">
                <input id="edit_searchInput" class="controls" type="text" placeholder="{{ __('storefront.common.enter_a_location') }}">
                <div id="edit_map"></div>
                <ul id="geoData">
                    <li style="display: none;">{{ __('storefront.common.full_address_label') }} <span id="location"></span></li>
                    <li style="display: none;">{{ __('storefront.common.postal_code_label') }} <span id="postal_code"></span></li>
                    <li style="display: none;">{{ __('storefront.common.country_label') }} <span id="country"></span></li>
                    <li style="display: none;">{{ __('storefront.common.latitude_label') }} <span id="lat"></span></li>
                    <li style="display: none;">{{ __('storefront.common.longitude_label') }} <span id="lon"></span></li>
                </ul>
            </div>
            <!-- Longitude -->
            <div class="row">
                <div class="col-md-2">
                    <label for="edit_longitude">{{ __('storefront.common.longitude')}}</label>
                </div>
                <div class="col-md-10 mb-3">
                    <input type="text" class="form-control rounded-0 @error('longitude', 'updateAddress') is-invalid @enderror" id="edit_longitude" name="longitude" value="{{ $keepInput ? old('longitude') : $address_data->longitude }}" readonly="">
                    @error('longitude', 'updateAddress')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>
            <!-- Latitude -->
            <div class="row">
                <div class="col-md-2">
                    <label for="edit_latitude">{{ __('storefront.common.latitude')}}</label>
                </div>
                <div class="col-md-10 mb-3">
                    <input type="text" class="form-control rounded-0 @error('latitude', 'updateAddress') is-invalid @enderror" id="edit_latitude" name="latitude" value="{{ $keepInput ? old('latitude') : $address_data->latitude }}" readonly="">
                    @error('latitude', 'updateAddress')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>
        @endif

        <!-- Postal code -->
        <div class="row">
            <div class="col-md-2">
                <label for="edit_address_postal_code">{{ __('storefront.common.postal_code')}}<x-required-mark /></label>
            </div>
            <div class="col-md-10 mb-3">
                <input type="text" id="edit_address_postal_code" class="form-control rounded-0 @error('postal_code', 'updateAddress') is-invalid @enderror" placeholder="{{ __('storefront.common.your_postal_code')}}" value="{{ $keepInput ? old('postal_code') : $address_data->postal_code }}" name="postal_code" maxlength="255" required>
                @error('postal_code', 'updateAddress')
                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>

        <!-- Phone -->
        <div class="row">
            <div class="col-md-2">
                <label for="edit_address_phone">{{ __('storefront.auth.phone')}}<x-required-mark /></label>
            </div>
            <div class="col-md-10 mb-3">
                <input type="text" id="edit_address_phone" class="form-control rounded-0 @error('phone', 'updateAddress') is-invalid @enderror" placeholder="{{ __('storefront.common.880')}}" value="{{ $keepInput ? old('phone') : $address_data->phone }}" name="phone" maxlength="255" required>
                @error('phone', 'updateAddress')
                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>

        <!-- Save button -->
        <div class="form-group text-right">
            <button type="submit" class="btn btn-primary rounded-0 w-150px">{{__('storefront.common.save')}}</button>
        </div>
    </div>
</form>
