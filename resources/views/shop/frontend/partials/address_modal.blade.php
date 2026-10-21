<!-- New Address Modal -->
<div class="modal fade" id="new-address-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ translate('New Address') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form class="form-default" role="form" action="{{ route('addresses.store') }}" method="POST">
                @csrf
                @php
                    $keepInput = $errors->storeAddress->any();
                @endphp
                <div class="modal-body c-scrollbar-light">
                    <div class="p-3">
                        <x-required-legend />
                        <!-- Address -->
                        <div class="row">
                            <div class="col-md-2">
                                <label for="new_address_address">{{ translate('Address')}}<x-required-mark /></label>
                            </div>
                            <div class="col-md-10 mb-3">
                                <textarea id="new_address_address" class="form-control rounded-0 @error('address', 'storeAddress') is-invalid @enderror" placeholder="{{ translate('Your Address')}}" rows="2" name="address" maxlength="255" required>{{ $keepInput ? old('address') : '' }}</textarea>
                                @error('address', 'storeAddress')
                                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- Country -->
                        <x-address-country-field id="new_address_country" bag="storeAddress" />

                        @if (get_setting('google_map') == 1)
                            <!-- Google Map -->
                            <div class="row mt-3 mb-3">
                                <input id="searchInput" class="controls" type="text" placeholder="{{translate('Enter a location')}}">
                                <div id="map"></div>
                                <ul id="geoData">
                                    <li style="display: none;">Full Address: <span id="location"></span></li>
                                    <li style="display: none;">Postal Code: <span id="postal_code"></span></li>
                                    <li style="display: none;">Country: <span id="country"></span></li>
                                    <li style="display: none;">Latitude: <span id="lat"></span></li>
                                    <li style="display: none;">Longitude: <span id="lon"></span></li>
                                </ul>
                            </div>
                            <!-- Longitude -->
                            <div class="row">
                                <div class="col-md-2">
                                    <label for="longitude">{{ translate('Longitude')}}</label>
                                </div>
                                <div class="col-md-10 mb-3">
                                    <input type="text" class="form-control rounded-0 @error('longitude', 'storeAddress') is-invalid @enderror" id="longitude" name="longitude" value="{{ $keepInput ? old('longitude') : '' }}" readonly="">
                                    @error('longitude', 'storeAddress')
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <!-- Latitude -->
                            <div class="row">
                                <div class="col-md-2">
                                    <label for="latitude">{{ translate('Latitude')}}</label>
                                </div>
                                <div class="col-md-10 mb-3">
                                    <input type="text" class="form-control rounded-0 @error('latitude', 'storeAddress') is-invalid @enderror" id="latitude" name="latitude" value="{{ $keepInput ? old('latitude') : '' }}" readonly="">
                                    @error('latitude', 'storeAddress')
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <!-- Postal code -->
                        <div class="row">
                            <div class="col-md-2">
                                <label for="new_address_postal_code">{{ translate('Postal code')}}<x-required-mark /></label>
                            </div>
                            <div class="col-md-10 mb-3">
                                <input type="text" id="new_address_postal_code" class="form-control rounded-0 @error('postal_code', 'storeAddress') is-invalid @enderror" placeholder="{{ translate('Your Postal Code')}}" name="postal_code" value="{{ $keepInput ? old('postal_code') : '' }}" maxlength="255" required>
                                @error('postal_code', 'storeAddress')
                                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- Phone -->
                        <div class="row">
                            <div class="col-md-2">
                                <label for="new_address_phone">{{ translate('Phone')}}<x-required-mark /></label>
                            </div>
                            <div class="col-md-10 mb-3">
                                <input type="text" id="new_address_phone" class="form-control rounded-0 @error('phone', 'storeAddress') is-invalid @enderror" placeholder="{{ translate('+')}}" name="phone" value="{{ $keepInput ? old('phone') : '' }}" maxlength="255" required>
                                @error('phone', 'storeAddress')
                                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                        <!-- Save button -->
                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-primary rounded-0 w-150px">{{translate('Save')}}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Address Modal -->
<div class="modal fade" id="edit-address-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ translate('New Address') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body c-scrollbar-light" id="edit_modal_body">
                {{-- After a failed update, the address's form again, with the input and the errors. --}}
                @php
                    $reopenedAddress = $errors->updateAddress->any() && is_numeric(old('address_id')) ? \App\Models\Address::find((int) old('address_id')) : null;
                    $reopenedAddress = $reopenedAddress !== null && \Illuminate\Support\Facades\Gate::allows('manage', $reopenedAddress) ? $reopenedAddress : null;
                @endphp
                @if ($reopenedAddress !== null)
                    @include('shop.frontend.partials.address_edit_modal', ['address_data' => $reopenedAddress])
                @endif
            </div>
        </div>
    </div>
</div>

@section('script')
    <script type="text/javascript">
        function add_new_address(){
            $('#new-address-modal').modal('show');
        }

        function edit_address(address) {
            var url = '{{ route("addresses.edit", ":id") }}';
            url = url.replace(':id', address);

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: url,
                type: 'GET',
                success: function (response) {
                    $('#edit_modal_body').html(response.html);
                    open_edit_address_modal(response.data.address_data);
                }
            });
        }

        function open_edit_address_modal(address_data) {
            $('#edit-address-modal').modal('show');
            AIZ.plugins.bootstrapSelect('refresh');

            @if (get_setting('google_map') == 1)
                var lat     = -33.8688;
                var long    = 151.2195;

                if(address_data.latitude && address_data.longitude) {
                    lat     = parseFloat(address_data.latitude);
                    long    = parseFloat(address_data.longitude);
                }

                initialize(lat, long, 'edit_');
            @endif
        }

        @if ($errors->storeAddress->any())
            $(function () { $('#new-address-modal').modal('show'); });
        @endif
        @if ($reopenedAddress !== null)
            $(function () { open_edit_address_modal(@json($reopenedAddress->only(['latitude', 'longitude']))); });
        @endif

        $(document).on('change', '[name=country_id]', function() {
            var country_id = $(this).val();
            get_states(country_id);
        });

        $(document).on('change', '[name=state_id]', function() {
            var state_id = $(this).val();
            get_city(state_id);
        });

        function get_states(country_id) {
            $('[name="state"]').html("");
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('get-state')}}",
                type: 'POST',
                data: {
                    country_id  : country_id
                },
                success: function (response) {
                    var obj = JSON.parse(response);
                    if(obj != '') {
                        $('[name="state_id"]').html(obj);
                        AIZ.plugins.bootstrapSelect('refresh');
                    }
                }
            });
        }

        function get_city(state_id) {
            $('[name="city"]').html("");
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('get-city')}}",
                type: 'POST',
                data: {
                    state_id: state_id
                },
                success: function (response) {
                    var obj = JSON.parse(response);
                    if(obj != '') {
                        $('[name="city_id"]').html(obj);
                        AIZ.plugins.bootstrapSelect('refresh');
                    }
                }
            });
        }
    </script>


    @if (get_setting('google_map') == 1)
        @include('shop.frontend.partials.google_map')
    @endif
@endsection
