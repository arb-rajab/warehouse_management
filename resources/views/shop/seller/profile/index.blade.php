@extends('shop.seller.layouts.app')

@section('panel_content')
    <div class="aiz-titlebar mt-2 mb-4">
      <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{ __('storefront.upload.manage_profile') }}</h1>
        </div>
      </div>
    </div>
    <form action="{{ route('seller.profile.update', $user->id) }}" method="POST" enctype="multipart/form-data">
        <input name="_method" type="hidden" value="POST">
        @csrf
        <x-required-legend />
        <!-- Basic Info-->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{ __('storefront.common.basic_info')}}</h5>
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="name">{{ __('storefront.common.your_name') }}<x-required-mark /></label>
                    <div class="col-md-10">
                        <input type="text" name="name" value="{{ $errors->sellerProfile->any() ? (is_scalar(old('name')) ? old('name') : '') : $user->name }}" id="name" class="form-control" placeholder="{{ __('storefront.common.your_name') }}" maxlength="191" required>
                        @error('name', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="phone">{{ __('storefront.auth.your_phone') }}</label>
                    <div class="col-md-10">
                        <input type="text" name="phone" value="{{ $errors->sellerProfile->any() ? (is_scalar(old('phone')) ? old('phone') : '') : $user->phone }}" id="phone" class="form-control" placeholder="{{ __('storefront.auth.your_phone')}}" maxlength="20">
                        @error('phone', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="seller-photo">{{ __('storefront.common.photo') }}</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ __('storefront.upload.browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                            <input type="hidden" name="photo" id="seller-photo" value="{{ $errors->sellerProfile->any() ? (is_scalar(old('photo')) ? old('photo') : '') : $user->avatar_original }}" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                        @error('photo', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="password">{{ __('storefront.auth.your_password') }}</label>
                    <div class="col-md-10">
                        <input type="password" name="new_password" id="password" class="form-control" placeholder="{{ __('storefront.auth.new_password') }}" minlength="6" maxlength="72" autocomplete="new-password">
                        <small class="form-text text-muted">{{ __('seller.profile.password_change_help') }}</small>
                        @error('new_password', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-form-label" for="confirm_password">{{ __('storefront.auth.confirm_password') }}</label>
                    <div class="col-md-10">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="{{ __('storefront.auth.confirm_password') }}" minlength="6" maxlength="72" autocomplete="new-password">
                        @error('confirm_password', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

            </div>
        </div>

        <!-- Payment System -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{ __('seller.profile.payment_setting')}}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <label class="col-md-3 col-form-label">{{ __('seller.profile.cash_payment') }}</label>
                    <div class="col-md-9">
                        <label class="aiz-switch aiz-switch-success mb-3">
                            <input value="1" name="cash_on_delivery_status" type="checkbox" @checked($errors->sellerProfile->any() ? (is_scalar(old('cash_on_delivery_status')) ? old('cash_on_delivery_status') : '') : $user->shop->cash_on_delivery_status)>
                            <span class="slider round"></span>
                        </label>
                        @error('cash_on_delivery_status', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="row">
                    <label class="col-md-3 col-form-label">{{ __('storefront.status.bank_payment') }}</label>
                    <div class="col-md-9">
                        <label class="aiz-switch aiz-switch-success mb-3">
                            <input value="1" name="bank_payment_status" type="checkbox" @checked($errors->sellerProfile->any() ? (is_scalar(old('bank_payment_status')) ? old('bank_payment_status') : '') : $user->shop->bank_payment_status)>
                            <span class="slider round"></span>
                        </label>
                        @error('bank_payment_status', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="row">
                    <label class="col-md-3 col-form-label" for="bank_name">{{ __('storefront.common.bank_name') }}</label>
                    <div class="col-md-9">
                        <input type="text" name="bank_name" value="{{ $errors->sellerProfile->any() ? (is_scalar(old('bank_name')) ? old('bank_name') : '') : $user->shop->bank_name }}" id="bank_name" class="form-control mb-3" placeholder="{{ __('storefront.common.bank_name')}}" maxlength="255">
                        @error('bank_name', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="row">
                    <label class="col-md-3 col-form-label" for="bank_acc_name">{{ __('seller.profile.bank_account_name') }}</label>
                    <div class="col-md-9">
                        <input type="text" name="bank_acc_name" value="{{ $errors->sellerProfile->any() ? (is_scalar(old('bank_acc_name')) ? old('bank_acc_name') : '') : $user->shop->bank_acc_name }}" id="bank_acc_name" class="form-control mb-3" placeholder="{{ __('seller.profile.bank_account_name')}}" maxlength="200">
                        @error('bank_acc_name', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="row">
                    <label class="col-md-3 col-form-label" for="bank_acc_no">{{ __('storefront.auth.bank_account_number') }}</label>
                    <div class="col-md-9">
                        <input type="text" name="bank_acc_no" value="{{ $errors->sellerProfile->any() ? (is_scalar(old('bank_acc_no')) ? old('bank_acc_no') : '') : $user->shop->bank_acc_no }}" id="bank_acc_no" class="form-control mb-3" placeholder="{{ __('storefront.auth.bank_account_number')}}" maxlength="50">
                        @error('bank_acc_no', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="row">
                    <label class="col-md-3 col-form-label" for="bank_routing_no">{{ __('seller.profile.bank_routing_number') }}</label>
                    <div class="col-md-9">
                        <input type="number" name="bank_routing_no" value="{{ $errors->sellerProfile->any() ? (is_scalar(old('bank_routing_no')) ? old('bank_routing_no') : '') : $user->shop->bank_routing_no }}" id="bank_routing_no" lang="en" class="form-control mb-3" placeholder="{{ __('seller.profile.bank_routing_number')}}" min="-2147483648" max="2147483647" step="1">
                        @error('bank_routing_no', 'sellerProfile')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group mb-0 text-right">
            <button type="submit" class="btn btn-primary">{{__('storefront.upload.update_profile')}}</button>
        </div>
    </form>

    <br>

    <!-- Address -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ __('storefront.common.address')}}</h5>
        </div>
        <div class="card-body">
            <div class="row gutters-10">
                @foreach ($addresses as $key => $address)
                    <div class="col-lg-4">
                        <div class="border p-3 pr-5 rounded mb-3 position-relative">
                            <div>
                                <span class="w-50 fw-600">{{ __('storefront.common.address') }}:</span>
                                <span class="ml-2">{{ $address->address }}</span>
                            </div>
                            {{--  <div>

                                <span class="w-50 fw-600">{{ __('storefront.common.postal_code_label') }}:</span>
                                <span class="ml-2">{{ $address->postal_code }}</span>
                            </div> --}}
                            <div>
                                <span class="w-50 fw-600">{{ __('storefront.common.city') }}:</span>
                                <span class="ml-2">{{ optional($address->city)->name }}</span>
                            </div>
                            <div>
                                <span class="w-50 fw-600">{{ __('storefront.common.state') }}:</span>
                                <span class="ml-2">{{ optional($address->state)->name }}</span>
                            </div>
                            <div>
                                <span class="w-50 fw-600">{{ __('storefront.common.country_label') }}:</span>
                                <span class="ml-2">{{ optional($address->country)->name }}</span>
                            </div>
                            <div>
                                <span class="w-50 fw-600">{{ __('storefront.auth.phone') }}:</span>
                                <span class="ml-2">{{ $address->phone }}</span>
                            </div>
                            @if ($address->set_default)
                                <div class="position-absolute right-0 bottom-0 pr-2 pb-3">
                                    <span class="badge badge-inline badge-primary">{{ __('storefront.common.default') }}</span>
                                </div>
                            @endif
                            <div class="dropdown position-absolute right-0 top-0">
                                <button class="btn bg-gray px-2" type="button" data-toggle="dropdown">
                                    <i class="la la-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                                    <a class="dropdown-item" onclick="edit_address('{{$address->id}}')">
                                        {{ __('storefront.common.edit') }}
                                    </a>
                                    @if (!$address->set_default)
                                        <a class="dropdown-item" data-method="post" href="{{ route('seller.addresses.set_default', $address->id) }}">{{ __('storefront.common.make_this_default') }}</a>
                                    @endif
                                    <a class="dropdown-item" data-method="post" href="{{ route('seller.addresses.destroy', $address->id) }}">{{ __('storefront.common.delete') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="col-lg-4 mx-auto" onclick="add_new_address()">
                    <div class="border p-3 rounded mb-3 c-pointer text-center bg-light">
                        <i class="la la-plus la-2x"></i>
                        <div class="alpha-7">{{ __('storefront.common.add_new_address') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Email -->
    <form action="{{ route('user.change.email') }}" method="POST">
        @csrf
        <x-required-legend />
        <div class="card">
          <div class="card-header">
              <h5 class="mb-0 h6">{{ __('storefront.auth.change_your_email')}}</h5>
          </div>
          <div class="card-body">
              <div class="row">
                  <div class="col-md-2">
                      <label for="seller-email">{{ __('storefront.auth.your_email') }}<x-required-mark /></label>
                  </div>
                  <div class="col-md-10">
                      <div class="input-group mb-3">
                        <input type="email" class="form-control" placeholder="{{ __('storefront.auth.your_email')}}" name="email" id="seller-email" value="{{ $errors->changeEmail->any() ? (is_scalar(old('email')) ? old('email') : '') : $user->email }}" maxlength="191" required />
                        <div class="input-group-append">
                           <button type="button" class="btn btn-outline-secondary new-email-verification">
                               <span class="d-none loading">
                                   <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>{{ __('storefront.auth.sending_email') }}
                               </span>
                               <span class="default">{{ __('storefront.auth.verify') }}</span>
                           </button>
                        </div>
                      </div>
                      @error('email', 'changeEmail')
                          <small class="form-text text-danger">{{ $message }}</small>
                      @enderror
                      <div class="form-group mb-0 text-right">
                          <button type="submit" class="btn btn-primary">{{__('storefront.auth.update_email')}}</button>
                      </div>
                  </div>
              </div>
          </div>
        </div>
    </form>

@endsection

@section('modal')
    {{-- New Address Modal --}}
    <div class="modal fade" id="new-address-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('storefront.common.new_address') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form class="form-default" role="form" action="{{ route('seller.addresses.store') }}" method="POST">
                    @csrf
                    @php
                        $keepInput = $errors->storeAddress->any();
                    @endphp
                    <div class="modal-body">
                        <div class="p-3">
                            <x-required-legend />
                            <div class="row">
                                <div class="col-md-2">
                                    <label for="new_address_address">{{ __('storefront.common.address')}}<x-required-mark /></label>
                                </div>
                                <div class="col-md-10 mb-3">
                                    <textarea id="new_address_address" class="form-control @error('address', 'storeAddress') is-invalid @enderror" placeholder="{{ __('storefront.common.your_address')}}" rows="2" name="address" maxlength="255" required>{{ $keepInput ? old('address') : '' }}</textarea>
                                    @error('address', 'storeAddress')
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <x-address-country-field id="new_address_country" bag="storeAddress" />

                            <div class="row">
                                <div class="col-md-2">
                                    <label for="new_address_state">{{ __('storefront.common.state')}}</label>
                                </div>
                                <div class="col-md-10 mb-3">
                                    <select id="new_address_state" class="form-control aiz-selectpicker @error('state_id', 'storeAddress') is-invalid @enderror" data-live-search="true" name="state_id">

                                    </select>
                                    @error('state_id', 'storeAddress')
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-2">
                                    <label for="new_address_city">{{ __('storefront.common.city')}}</label>
                                </div>
                                <div class="col-md-10 mb-3">
                                    <select id="new_address_city" class="form-control aiz-selectpicker @error('city_id', 'storeAddress') is-invalid @enderror" data-live-search="true" name="city_id">

                                    </select>
                                    @error('city_id', 'storeAddress')
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            @if (get_setting('google_map') == 1)
                                <div class="row">
                                    <input id="searchInput" class="controls" type="text" placeholder="{{__('storefront.common.enter_a_location')}}">
                                    <div id="map"></div>
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
                                        <label for="longitude">{{ __('storefront.common.longitude_label') }}</label>
                                    </div>
                                    <div class="col-md-10 mb-3">
                                        <input type="text" class="form-control @error('longitude', 'storeAddress') is-invalid @enderror" id="longitude" name="longitude" value="{{ $keepInput ? old('longitude') : '' }}" readonly="">
                                        @error('longitude', 'storeAddress')
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-2">
                                        <label for="latitude">{{ __('storefront.common.latitude_label') }}</label>
                                    </div>
                                    <div class="col-md-10 mb-3">
                                        <input type="text" class="form-control @error('latitude', 'storeAddress') is-invalid @enderror" id="latitude" name="latitude" value="{{ $keepInput ? old('latitude') : '' }}" readonly="">
                                        @error('latitude', 'storeAddress')
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                    </div>
                                </div>
                            @endif

                            <div class="row">
                                {{--  <div class="col-md-2">
                                    <label>{{ __('storefront.common.postal_code_label')}}</label>
                                </div>
                                <div class="col-md-10">
                                    <input type="text" class="form-control mb-3" placeholder="{{ __('storefront.common.your_postal_code')}}" name="postal_code" value="" required>
                                </div> --}}
                            </div>
                            <div class="row">
                                <div class="col-md-2">
                                    <label for="new_address_phone">{{ __('storefront.auth.phone')}}<x-required-mark /></label>
                                </div>
                                <div class="col-md-10 mb-3">
                                    <input type="text" id="new_address_phone" class="form-control @error('phone', 'storeAddress') is-invalid @enderror" placeholder="{{ __('storefront.common.plus')}}" name="phone" value="{{ $keepInput ? (is_scalar(old('phone')) ? old('phone') : '') : '' }}" maxlength="255" required>
                                    @error('phone', 'storeAddress')
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-group text-right">
                                <button type="submit" class="btn btn-sm btn-primary">{{__('storefront.common.save')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Address Modal --}}
    <div class="modal fade" id="edit-address-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('storefront.common.new_address') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body" id="edit_modal_body">
                    {{-- After a failed update, the address's form again, with the input and the errors. --}}
                    @php
                        $reopenedAddress = $errors->updateAddress->any() && is_numeric(old('address_id')) ? \App\Models\Address::find((int) old('address_id')) : null;
                        $reopenedAddress = $reopenedAddress !== null && \Illuminate\Support\Facades\Gate::allows('manage', $reopenedAddress) ? $reopenedAddress : null;
                    @endphp
                    @if ($reopenedAddress !== null)
                        @include('shop.seller.profile.address_edit_modal', [
                            'address_data' => $reopenedAddress,
                            'states' => \App\Models\State::where('status', 1)->where('country_id', $reopenedAddress->country_id)->get(),
                            'cities' => \App\Models\City::where('status', 1)->where('state_id', $reopenedAddress->state_id)->get(),
                        ])
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script type="text/javascript">

        $('.new-email-verification').on('click', function() {
            $(this).find('.loading').removeClass('d-none');
            $(this).find('.default').addClass('d-none');
            var email = $("input[name=email]").val();

            $.post('{{ route('user.new.verify') }}', {_token:'{{ csrf_token() }}', email: email}, function(data){
                data = JSON.parse(data);
                $('.default').removeClass('d-none');
                $('.loading').addClass('d-none');
                if(data.status == 2)
                    AIZ.plugins.notify('warning', data.message);
                else if(data.status == 1)
                    AIZ.plugins.notify('success', data.message);
                else
                    AIZ.plugins.notify('danger', data.message);
            });
        });

        function add_new_address(){
            $('#new-address-modal').modal('show');
        }

        function edit_address(address) {
            var url = '{{ route("seller.addresses.edit", ":id") }}';
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
                var lat     = defaultMapLocation.latitude;
                var long    = defaultMapLocation.longitude;

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
                url: "{{route('seller.get-state')}}",
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
                url: "{{route('seller.get-city')}}",
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
