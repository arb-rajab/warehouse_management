@extends('shop.seller.layouts.app')

@section('panel_content')
    @php
        $shopErrors = $errors->getBag('sellerShop');
        $shopValue = function ($field, $current) use ($shopErrors) {
            $value = $shopErrors->any() ? old($field, $current) : $current;
            return is_scalar($value) || $value === null ? $value : '';
        };
    @endphp

    <div class="aiz-titlebar mt-2 mb-4">
      <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{ translate('Shop Settings')}}
                <span class="ml-3 fs-13">(<a href="{{ route('shop.visit', $shop->slug) }}" class="btn btn-link btn-sm px-0" target="_blank">{{ translate('Visit Shop')}} <i class="las la-arrow-right"></i></a>)</span>
            </h1>
        </div>
      </div>
    </div>

    <!-- Basic Info -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Basic Info') }}</h5>
        </div>
        <div class="card-body">
            <form class="" action="{{ route('seller.shop.update') }}" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="section" value="basic">
                @csrf
                <x-required-legend />
                <div class="row">
                    <label class="col-md-2 col-form-label" for="shop-name">{{ translate('Shop Name') }}<x-required-mark /></label>
                    <div class="col-md-10">
                        <input type="text" class="form-control mb-3" placeholder="{{ translate('Shop Name')}}" id="shop-name" name="name" value="{{ $shopValue('name', $shop->name) }}" required maxlength="200">
                        @error('name', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-2 col-form-label" for="shop-logo">{{ translate('Shop Logo') }}</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                            <input type="hidden" id="shop-logo" name="logo" value="{{ $shopValue('logo', $shop->logo) }}" class="selected-files">
                        @error('logo', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-2">
                        <label for="shop-phone">{{ translate('Shop Phone') }} </label>
                    </div>
                    <div class="col-md-10">
                        <input type="text" class="form-control mb-3" placeholder="{{ translate('Phone')}}" id="shop-phone" name="phone" value="{{ $shopValue('phone', $shop->phone) }}" maxlength="255">
                        @error('phone', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <label class="col-md-2 col-form-label" for="shop-address">{{ translate('Shop Address') }}<x-required-mark /> </label>
                    <div class="col-md-10">
                        <input type="text" class="form-control mb-3" placeholder="{{ translate('Address')}}" id="shop-address" name="address" value="{{ $shopValue('address', $shop->address) }}" required maxlength="500">
                        @error('address', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                @if (get_setting('shipping_type') == 'seller_wise_shipping')
                    <div class="row">
                        <div class="col-md-2">
                            <label for="shop-shipping_cost">{{ translate('Shipping Cost')}}@if(get_setting('shipping_type') === 'seller_wise_shipping')<x-required-mark />@endif </label>
                        </div>
                        <div class="col-md-10">
                            <input type="number" lang="en" min="0" class="form-control mb-3" placeholder="{{ translate('Shipping Cost')}}" id="shop-shipping_cost" name="shipping_cost" value="{{ $shopValue('shipping_cost', $shop->shipping_cost) }}" @required(get_setting('shipping_type') === 'seller_wise_shipping') step="any" max="999999999999999999.99">
                        @error('shipping_cost', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endif 
                <div class="row">
                    <label class="col-md-2 col-form-label" for="shop-meta_title">{{ translate('Meta Title') }}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control mb-3" placeholder="{{ translate('Meta Title')}}" id="shop-meta_title" name="meta_title" value="{{ $shopValue('meta_title', $shop->meta_title) }}" maxlength="255">
                        @error('meta_title', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <label class="col-md-2 col-form-label" for="shop-meta_description">{{ translate('Meta Description') }}</label>
                    <div class="col-md-10">
                        <textarea id="shop-meta_description" name="meta_description" rows="3" class="form-control mb-3" maxlength="65535">{{ $shopValue('meta_description', $shop->meta_description) }}</textarea>
                        @error('meta_description', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delivery Boy Pickup Point -->
    @if (addon_is_activated('delivery_boy'))
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{ translate('Delivery Boy Pickup Point') }}</h5>
            </div>
            <div class="card-body">
                <form class="" action="{{ route('seller.shop.update') }}" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="section" value="pickup">
                    @csrf
                    <p class="text-muted">{{ translate('Enter both latitude and longitude, or leave both empty.') }}</p>

                    @if (get_setting('google_map') == 1)
                        <div class="row mb-3">
                            <input id="searchInput" class="controls" type="text" placeholder="{{translate('Enter a location')}}">
                            <div id="map"></div>
                            <ul id="geoData">
                                <li style="display: none;">{{ translate('Full Address') }}: <span id="location"></span></li>
                                <li style="display: none;">{{ translate('Postal Code') }}: <span id="postal_code"></span></li>
                                <li style="display: none;">{{ translate('Country') }}: <span id="country"></span></li>
                                <li style="display: none;">{{ translate('Latitude') }}: <span id="lat"></span></li>
                                <li style="display: none;">{{ translate('Longitude') }}: <span id="lon"></span></li>
                            </ul>
                        </div>
                        <div class="row">
                            <div class="col-md-2" id="">
                                <label for="longitude">{{ translate('Longitude') }}</label>
                            </div>
                            <div class="col-md-10" id="">
                                <input type="number" class="form-control mb-3" id="longitude" name="delivery_pickup_longitude" readonly="" value="{{ $shopValue('delivery_pickup_longitude', $shop->delivery_pickup_longitude) }}" min="-180" max="180" step="any">
                        @error('delivery_pickup_longitude', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2" id="">
                                <label for="latitude">{{ translate('Latitude') }}</label>
                            </div>
                            <div class="col-md-10" id="">
                                <input type="number" class="form-control mb-3" id="latitude" name="delivery_pickup_latitude" readonly="" value="{{ $shopValue('delivery_pickup_latitude', $shop->delivery_pickup_latitude) }}" min="-90" max="90" step="any">
                        @error('delivery_pickup_latitude', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @else
                        <div class="row">
                            <div class="col-md-2" id="">
                                <label for="longitude">{{ translate('Longitude') }}</label>
                            </div>
                            <div class="col-md-10" id="">
                                <input type="number" class="form-control mb-3" id="longitude" name="delivery_pickup_longitude" value="{{ $shopValue('delivery_pickup_longitude', $shop->delivery_pickup_longitude) }}" min="-180" max="180" step="any">
                        @error('delivery_pickup_longitude', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2" id="">
                                <label for="latitude">{{ translate('Latitude') }}</label>
                            </div>
                            <div class="col-md-10" id="">
                                <input type="number" class="form-control mb-3" id="latitude" name="delivery_pickup_latitude" value="{{ $shopValue('delivery_pickup_latitude', $shop->delivery_pickup_latitude) }}" min="-90" max="90" step="any">
                        @error('delivery_pickup_latitude', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @endif

                    <div class="form-group mb-0 text-right">
                        <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Banner Settings -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Banner Settings') }}</h5>
        </div>
        <div class="card-body">
            <form class="" action="{{ route('seller.shop.update') }}" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="section" value="banners">
                @csrf
                <!-- Top Banner -->
                <div class="row mb-3">
                    <label class="col-md-2 col-form-label" for="shop-top_banner">{{ translate('Top Banner') }} (1920x360)</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                            <input type="hidden" id="shop-top_banner" name="top_banner" value="{{ $shopValue('top_banner', $shop->top_banner) }}" class="selected-files">
                        @error('top_banner', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="file-preview box sm">
                        </div>
                        <small class="text-muted">{{ translate('We had to limit height to maintian consistancy. In some device both side of the banner might be cropped for height limitation.') }}</small>
                    </div>
                </div>
                <!-- Slider Banners -->
                <div class="row mb-3">
                    <label class="col-md-2 col-form-label" for="shop-sliders">{{ translate('Slider Banners') }} (1500x450)</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                            <input type="hidden" id="shop-sliders" name="sliders" value="{{ $shopValue('sliders', $shop->sliders) }}" class="selected-files">
                        @error('sliders', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="file-preview box sm">
                        </div>
                        <small class="text-muted">{{ translate('We had to limit height to maintian consistancy. In some device both side of the banner might be cropped for height limitation.') }}</small>
                    </div>
                </div>
                <!-- Banner Full width 1 -->
                <div class="row mb-3">
                    <label class="col-md-2 col-form-label" for="shop-banner_full_width_1">{{ translate('Banner Full width 1') }}</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                            <input type="hidden" id="shop-banner_full_width_1" name="banner_full_width_1" value="{{ $shopValue('banner_full_width_1', $shop->banner_full_width_1) }}" class="selected-files">
                        @error('banner_full_width_1', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
                <!-- Banners half width -->
                <div class="row mb-3">
                    <label class="col-md-2 col-form-label" for="shop-banners_half_width">{{ translate('Banners half width') }} ({{ translate('2 Equal Banners') }})</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                            <input type="hidden" id="shop-banners_half_width" name="banners_half_width" value="{{ $shopValue('banners_half_width', $shop->banners_half_width) }}" class="selected-files">
                        @error('banners_half_width', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
                <!-- Banner Full width 2 -->
                <div class="row mb-3">
                    <label class="col-md-2 col-form-label" for="shop-banner_full_width_2">{{ translate('Banner Full width 2') }}</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                            <input type="hidden" id="shop-banner_full_width_2" name="banner_full_width_2" value="{{ $shopValue('banner_full_width_2', $shop->banner_full_width_2) }}" class="selected-files">
                        @error('banner_full_width_2', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
                <!-- Save Button -->
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Social Media Link -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Social Media Link') }}</h5>
        </div>
        <div class="card-body">
            <form class="" action="{{ route('seller.shop.update') }}" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="section" value="social">
                @csrf
                <div class="form-box-content p-3">
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label" for="shop-facebook">{{ translate('Facebook') }}</label>
                        <div class="col-md-10">
                            <input type="url" class="form-control" placeholder="{{ translate('Facebook')}}" id="shop-facebook" name="facebook" value="{{ $shopValue('facebook', $shop->facebook) }}" maxlength="255">
                        @error('facebook', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="text-muted">{{ translate('Insert link with https ') }}</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label" for="shop-instagram">{{ translate('Instagram') }}</label>
                        <div class="col-md-10">
                            <input type="url" class="form-control" placeholder="{{ translate('Instagram')}}" id="shop-instagram" name="instagram" value="{{ $shopValue('instagram', $shop->instagram) }}" maxlength="255">
                        @error('instagram', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="text-muted">{{ translate('Insert link with https ') }}</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label" for="shop-twitter">{{ translate('Twitter') }}</label>
                        <div class="col-md-10">
                            <input type="url" class="form-control" placeholder="{{ translate('Twitter')}}" id="shop-twitter" name="twitter" value="{{ $shopValue('twitter', $shop->twitter) }}" maxlength="255">
                        @error('twitter', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="text-muted">{{ translate('Insert link with https ') }}</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label" for="shop-google">{{ translate('Google') }}</label>
                        <div class="col-md-10">
                            <input type="url" class="form-control" placeholder="{{ translate('Google')}}" id="shop-google" name="google" value="{{ $shopValue('google', $shop->google) }}" maxlength="255">
                        @error('google', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="text-muted">{{ translate('Insert link with https ') }}</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label" for="shop-youtube">{{ translate('Youtube') }}</label>
                        <div class="col-md-10">
                            <input type="url" class="form-control" placeholder="{{ translate('Youtube')}}" id="shop-youtube" name="youtube" value="{{ $shopValue('youtube', $shop->youtube) }}" maxlength="255">
                        @error('youtube', 'sellerShop')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="text-muted">{{ translate('Insert link with https ') }}</small>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('script')

    @if (addon_is_activated('delivery_boy') && get_setting('google_map') == 1)
            
    <script>
        {{-- Where the pin starts while the shop has no pick-up point: the admin's default location, else Amsterdam. --}}
        var defaultMapLocation = @json(\App\Support\DefaultMapLocation::coordinates());

        function initialize(id_format = '') {
            var lat = defaultMapLocation.latitude;
            var long = defaultMapLocation.longitude;

            if (document.getElementById('latitude').value != '' &&
                document.getElementById('longitude').value != '') {
                lat = parseFloat(document.getElementById('latitude').value);
                long = parseFloat(document.getElementById('longitude').value);
            }


            var map = new google.maps.Map(document.getElementById('map'), {
                center: {
                    lat: lat,
                    lng: long
                },
                zoom: 13
            });

            var myLatlng = new google.maps.LatLng(lat, long);

            var input = document.getElementById(id_format + 'searchInput');
            // console.log(input);
            map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

            var autocomplete = new google.maps.places.Autocomplete(input);

            autocomplete.bindTo('bounds', map);

            var infowindow = new google.maps.InfoWindow();
            var marker = new google.maps.Marker({
                map: map,
                position: myLatlng,
                anchorPoint: new google.maps.Point(0, -29),
                draggable: true,
            });

            map.addListener('click', function(event) {
                marker.setPosition(event.latLng);
                document.getElementById(id_format + 'latitude').value = event.latLng.lat();
                document.getElementById(id_format + 'longitude').value = event.latLng.lng();
                infowindow.setContent('Latitude: ' + event.latLng.lat() + '<br>Longitude: ' + event.latLng.lng());
                infowindow.open(map, marker);
            });

            google.maps.event.addListener(marker, 'dragend', function(event) {
                document.getElementById(id_format + 'latitude').value = event.latLng.lat();
                document.getElementById(id_format + 'longitude').value = event.latLng.lng();
                infowindow.setContent('Latitude: ' + event.latLng.lat() + '<br>Longitude: ' + event.latLng.lng());
                infowindow.open(map, marker);
            });

            autocomplete.addListener('place_changed', function() {
                infowindow.close();
                marker.setVisible(false);
                var place = autocomplete.getPlace();

                if (!place.geometry) {
                    window.alert("Autocomplete's returned place contains no geometry");
                    return;
                }

                // If the place has a geometry, then present it on a map.
                if (place.geometry.viewport) {
                    map.fitBounds(place.geometry.viewport);
                } else {
                    map.setCenter(place.geometry.location);
                    map.setZoom(17);
                }
                /*
                marker.setIcon(({
                    url: place.icon,
                    size: new google.maps.Size(71, 71),
                    origin: new google.maps.Point(0, 0),
                    anchor: new google.maps.Point(17, 34),
                    scaledSize: new google.maps.Size(35, 35)
                }));
                */
                marker.setPosition(place.geometry.location);
                marker.setVisible(true);

                var address = '';
                if (place.address_components) {
                    address = [
                        (place.address_components[0] && place.address_components[0].short_name || ''),
                        (place.address_components[1] && place.address_components[1].short_name || ''),
                        (place.address_components[2] && place.address_components[2].short_name || '')
                    ].join(' ');
                }

                infowindow.setContent('<div><strong>' + place.name + '</strong><br>' + address);
                infowindow.open(map, marker);

                //Location details
                for (var i = 0; i < place.address_components.length; i++) {
                    if (place.address_components[i].types[0] == 'postal_code') {
                        document.getElementById('postal_code').innerHTML = place.address_components[i].long_name;
                    }
                    if (place.address_components[i].types[0] == 'country') {
                        document.getElementById('country').innerHTML = place.address_components[i].long_name;
                    }
                }
                document.getElementById('location').innerHTML = place.formatted_address;
                document.getElementById(id_format + 'latitude').value = place.geometry.location.lat();
                document.getElementById(id_format + 'longitude').value = place.geometry.location.lng();
            });

        }
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&language=en&callback=initialize" async defer></script>

    @endif

@endsection
