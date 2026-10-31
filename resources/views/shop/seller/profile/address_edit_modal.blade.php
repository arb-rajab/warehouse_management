<form class="form-default" role="form" action="{{ route('seller.addresses.update', $address_data->id) }}" method="POST">
    @csrf
    @php
        $keepInput = $errors->updateAddress->any();
    @endphp
    {{-- Sent back with the input, so a failed update can reopen this address's form. --}}
    <input type="hidden" name="address_id" value="{{ $address_data->id }}">
    <div class="p-3">
        <x-required-legend />
        <div class="row">
            <div class="col-md-2">
                <label for="edit_address_address">{{ __('storefront.common.address')}}<x-required-mark /></label>
            </div>
            <div class="col-md-10 mb-3">
                <textarea id="edit_address_address" class="form-control @error('address', 'updateAddress') is-invalid @enderror" placeholder="{{ __('storefront.common.your_address')}}" rows="2" name="address" maxlength="255" required>{{ $keepInput ? old('address') : $address_data->address }}</textarea>
                @error('address', 'updateAddress')
                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>
        <x-address-country-field id="edit_country" bag="updateAddress" :selected="$address_data->country_id" />

        <div class="row">
            <div class="col-md-2">
                <label for="edit_state">{{ __('storefront.common.state')}}</label>
            </div>
            <div class="col-md-10 mb-3">
                <select class="form-control aiz-selectpicker @error('state_id', 'updateAddress') is-invalid @enderror" name="state_id" id="edit_state" data-live-search="true">
                    <option value="">{{ __('storefront.common.select_state') }}</option>
                    @foreach ($states as $key => $state)
                        <option value="{{ $state->id }}" @selected((string) $state->id === (string) ($keepInput ? old('state_id') : $address_data->state_id))>
                            {{ $state->name }}
                        </option>
                    @endforeach
                </select>
                @error('state_id', 'updateAddress')
                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-2">
                <label for="edit_city">{{ __('storefront.common.city')}}</label>
            </div>
            <div class="col-md-10 mb-3">
                <select class="form-control aiz-selectpicker @error('city_id', 'updateAddress') is-invalid @enderror" data-live-search="true" name="city_id" id="edit_city">
                    <option value="">{{ __('storefront.common.select_city') }}</option>
                    @foreach ($cities as $key => $city)
                        <option value="{{ $city->id }}" @selected((string) $city->id === (string) ($keepInput ? old('city_id') : $address_data->city_id))>
                            {{ $city->name }}
                        </option>
                    @endforeach
                </select>
                @error('city_id', 'updateAddress')
                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>

        @if (get_setting('google_map') == 1)
            <div class="row">
                <input id="edit_searchInput" class="controls" type="text" placeholder="{{ __('storefront.common.enter_a_location') }}">
                <div id="edit_map"></div>
                <ul id="geoData">
                    <li style="display: none;">{{ __('storefront.common.full_address_label') }}: <span id="location"></span></li>
                    <li style="display: none;">{{ __('storefront.common.postal_code_label') }}: <span id="postal_code"></span></li>
                    <li style="display: none;">{{ __('storefront.common.country_label') }}: <span id="country"></span></li>
                    <li style="display: none;">{{ __('storefront.common.latitude_label') }}: <span id="lat"></span></li>
                    <li style="display: none;">{{ __('storefront.common.longitude_label') }}: <span id="lon"></span></li>
                </ul>
            </div>

            <div class="row">
                <div class="col-md-2">
                    <label for="edit_longitude">{{ __('storefront.common.longitude_label') }}</label>
                </div>
                <div class="col-md-10 mb-3">
                    <input type="text" class="form-control @error('longitude', 'updateAddress') is-invalid @enderror" id="edit_longitude" name="longitude" value="{{ $keepInput ? old('longitude') : $address_data->longitude }}" readonly="">
                    @error('longitude', 'updateAddress')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">
                    <label for="edit_latitude">{{ __('storefront.common.latitude_label') }}</label>
                </div>
                <div class="col-md-10 mb-3">
                    <input type="text" class="form-control @error('latitude', 'updateAddress') is-invalid @enderror" id="edit_latitude" name="latitude" value="{{ $keepInput ? old('latitude') : $address_data->latitude }}" readonly="">
                    @error('latitude', 'updateAddress')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>
        @endif

        {{-- The postal code is not asked here (it was commented out before these rules); see ValidatesAddress. --}}
        <div class="row">
            <div class="col-md-2">
                <label for="edit_address_phone">{{ __('storefront.auth.phone')}}<x-required-mark /></label>
            </div>
            <div class="col-md-10 mb-3">
                <input type="text" id="edit_address_phone" class="form-control @error('phone', 'updateAddress') is-invalid @enderror" placeholder="{{ __('storefront.common.plus')}}" value="{{ $keepInput ? old('phone') : $address_data->phone }}" name="phone" maxlength="255" required>
                @error('phone', 'updateAddress')
                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
        </div>
        <div class="form-group text-right">
            <button type="submit" class="btn btn-sm btn-primary">{{__('storefront.common.save')}}</button>
        </div>
    </div>
</form>
