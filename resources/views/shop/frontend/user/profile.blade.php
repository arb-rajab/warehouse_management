@extends('shop.frontend.layouts.user_panel')

@section('panel_content')
    <div class="aiz-titlebar mb-4">
      <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="fs-20 fw-700 text-dark">{{ __('storefront.upload.manage_profile') }}</h1>
        </div>
      </div>
    </div>

    <!-- Basic Info -->
    <div class="card rounded-0 shadow-none border">
        <div class="card-header pt-4 border-bottom-0">
            <h5 class="mb-0 fs-18 fw-700 text-dark">{{ __('storefront.common.basic_info')}}</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('user.profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <!-- Name-->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14 fs-14">{{ __('storefront.common.full_name') }}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" placeholder="{{ __('storefront.common.full_name') }}" name="name" value="{{ Auth::user()->name }}">
                    </div>
                </div>
                <!-- Company Name-->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14 fs-14">{{ __('storefront.common.company_name') }}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" placeholder="{{ __('storefront.common.company_name') }}" name="company_name" value="{{ Auth::user()->company_name }}">
                    </div>
                </div>
                <!-- Phone-->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14">{{ __('storefront.auth.your_phone') }}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" placeholder="{{ __('storefront.auth.your_phone')}}" name="phone" value="{{ Auth::user()->phone }}">
                    </div>
                </div>
                <!-- tax number -->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14 fs-14">{{ __('storefront.common.tax_number') }}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" placeholder="{{ __('storefront.common.tax_number') }}" name="tax_number" value="{{ Auth::user()->tax_number }}">
                    </div>
                </div>

                <!-- bank account number -->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14 fs-14">{{ __('storefront.auth.bank_account_number') }}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" placeholder="{{ __('storefront.auth.bank_account_number') }}" name="bank_account_number" value="{{ Auth::user()->bank_account_number }}">
                    </div>
                </div>

                <!-- Company Address -->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14 fs-14">{{ __('storefront.common.company_address') }}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" placeholder="{{ __('storefront.common.company_address') }}" name="company_address" value="{{ Auth::user()->company_address }}">
                    </div>
                </div>


                <!-- Shipping Address -->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14 fs-14">{{ __('storefront.checkout.shipping_address') }}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" placeholder="{{ __('storefront.checkout.shipping_address') }}" name="shipping_address" value="{{ Auth::user()->shipping_address }}">
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14 fs-14">{{ __('storefront.common.country') }}</label>
                    <div class="col-md-10">
                        <select class="form-control aiz-selectpicker" name="country">
                            @foreach ($countries as $country)
                                <option value="{{ $country->name }}" @if ($country->name == Auth::user()->country) selected @endif>{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <!-- Photo-->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14">{{ __('storefront.common.photo') }}</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium rounded-0">{{ __('storefront.upload.browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                            <input type="hidden" name="photo" value="{{ Auth::user()->avatar_original }}" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
                <!-- Trade license-->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14">{{ __('storefront.common.trade_license') }}</label>
                    <div class="col-md-10">
                        <div class="input-group" style="cursor: pointer;" onclick="document.getElementById('trade_license').click()">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium rounded-0">{{ __('storefront.upload.browse') }}</div>
                            </div>
                            <div class="form-control file-amount" id="file-name-label">{{ __('storefront.upload.choose_file') }}</div>
                            <input type="file" hidden class="custom-file-input" id="trade_license" name="trade_license" value="{{ Auth::user()->trade_license }}">
                        </div>
                    </div>
                </div>
                @if (Auth::user()->trade_license)
                <div class="row align-items-center mb-3">
                    <div class="col-6">
                    </div>
                    <div class="col-6 text-right">
                        <a href="{{ route('profile.trade_license') }}" class="text-blue fs-10 fs-md-12 fw-700 hov-text-primary animate-underline-primary" target="_blank">{{ __('storefront.common.show_your_trade_license') }}</a>
                    </div>
                </div>
                @endif
                <!-- Password-->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14">{{ __('storefront.auth.your_password') }}</label>
                    <div class="col-md-10">
                        <input type="password" class="form-control rounded-0" placeholder="{{ __('storefront.auth.new_password') }}" name="new_password">
                    </div>
                </div>
                <!-- Confirm Password-->
                <div class="form-group row">
                    <label class="col-md-2 col-form-label fs-14">{{ __('storefront.auth.confirm_password') }}</label>
                    <div class="col-md-10">
                        <input type="password" class="form-control rounded-0" placeholder="{{ __('storefront.auth.confirm_password') }}" name="confirm_password">
                    </div>
                </div>
                <!-- Submit Button-->
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-primary rounded-0 w-150px mt-3">{{__('storefront.upload.update_profile')}}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Address -->
    <div class="card rounded-0 shadow-none border">
        <div class="card-header pt-4 border-bottom-0">
            <h5 class="mb-0 fs-18 fw-700 text-dark">{{ __('storefront.checkout.shipping_address')}}</h5>
        </div>
        <div class="card-body">
            @foreach (Auth::user()->addresses()->with(\App\Models\Address::WITH_PLACE)->get() as $key => $address)
                <div class="">
                    <div class="border p-4 mb-4 position-relative">
                        <div class="row fs-14 mb-2 mb-md-0">
                            <span class="col-md-2 text-secondary">{{ __('storefront.checkout.shipping_address') }}:</span>
                            <span class="col-md-8 text-dark">{{ $address->address }}</span>
                        </div>
                        {{--
                        <div class="row fs-14 mb-2 mb-md-0">
                            <span class="col-md-2 text-secondary">{{ __('storefront.common.postal_code') }}:</span>
                            <span class="col-md-10 text-dark">{{ $address->postal_code }}</span>
                        </div>  --}}
                        <div class="row fs-14 mb-2 mb-md-0">
                            <span class="col-md-2 text-secondary">{{ __('storefront.common.city') }}:</span>
                            <span class="col-md-10 text-dark">{{ optional($address->city)->name }}</span>
                        </div>
                        <div class="row fs-14 mb-2 mb-md-0">
                            <span class="col-md-2 text-secondary">{{ __('storefront.common.state') }}:</span>
                            <span class="col-md-10 text-dark">{{ optional($address->state)->name }}</span>
                        </div>
                        <div class="row fs-14 mb-2 mb-md-0">
                            <span class="col-md-2 text-secondary">{{ __('storefront.common.country') }}:</span>
                            <span class="col-md-10 text-dark">{{ optional($address->country)->name }}</span>
                        </div>
                        <div class="row fs-14 mb-2 mb-md-0">
                            <span class="col-md-2 text-secondary text-secondary">{{ __('storefront.auth.phone') }}:</span>
                            <span class="col-md-10 text-dark">{{ $address->phone }}</span>
                        </div>
                        <div class="row fs-14 mb-2 mb-md-0">
                            <span class="col-md-2 text-secondary text-secondary">{{ __('storefront.common.postal_code') }}:</span>
                            <span class="col-md-10 text-dark">{{ $address->postal_code }}</span>
                        </div>
                        @if ($address->set_default)
                            <div class="absolute-md-top-right pt-2 pt-md-4 pr-md-5">
                                <span class="badge badge-inline badge-warning text-white p-3 fs-12" style="border-radius: 25px; min-width: 80px !important;">{{ __('storefront.common.default') }}</span>
                            </div>
                        @endif
                        <div class="dropdown position-absolute right-0 top-0 pt-4 mr-1">
                            <button class="btn bg-gray text-white px-1 py-1" type="button" data-toggle="dropdown">
                                <i class="la la-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item" onclick="edit_address('{{$address->id}}')">
                                    {{ __('storefront.common.edit') }}
                                </a>
                                @if (!$address->set_default)
                                    <a class="dropdown-item" data-method="post" href="{{ route('addresses.set_default', $address->id) }}">{{ __('storefront.common.make_this_default') }}</a>
                                @endif
                                <a class="dropdown-item" data-method="post" href="{{ route('addresses.destroy', $address->id) }}">{{ __('storefront.common.delete') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            <!-- Add New Address -->
            <div class="" onclick="add_new_address()">
                <div class="border p-3 mb-3 c-pointer text-center bg-light has-transition hov-bg-soft-light">
                    <i class="la la-plus la-2x"></i>
                    <div class="alpha-7 fs-14 fw-700">{{ __('storefront.common.add_new_address') }}</div>
                </div>
            </div>
        </div>
    </div>


    <!-- Change Email -->
    <form action="{{ route('user.change.email') }}" method="POST">
        @csrf
        <div class="card rounded-0 shadow-none border">
          <div class="card-header pt-4 border-bottom-0">
              <h5 class="mb-0 fs-18 fw-700 text-dark">{{ __('storefront.auth.change_your_email')}}</h5>
          </div>
          <div class="card-body">
              <div class="row">
                  <div class="col-md-2">
                      <label class="fs-14">{{ __('storefront.auth.your_email') }}</label>
                  </div>
                  <div class="col-md-10">
                      <div class="input-group mb-3">
                        <input type="email" class="form-control rounded-0" placeholder="{{ __('storefront.auth.your_email')}}" name="email" value="{{ Auth::user()->email }}" />
                        <div class="input-group-append">
                           <button type="button" class="btn btn-outline-secondary new-email-verification">
                               <span class="d-none loading">
                                   <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>{{ __('storefront.auth.sending_email') }}
                               </span>
                               <span class="default">{{ __('storefront.auth.verify') }}</span>
                           </button>
                        </div>
                      </div>
                      <div class="form-group mb-0 text-right">
                          <button type="submit" class="btn btn-primary rounded-0 w-150px mt-3">{{__('storefront.auth.update_email')}}</button>
                      </div>
                  </div>
              </div>
          </div>
        </div>
    </form>

@endsection

@section('modal')
    <!-- Address modal -->
    @include('shop.frontend.partials.address_modal')
@endsection
<script>
    document.addEventListener('DOMContentLoaded', function() {

        const fileInput = document.getElementById('trade_license');
        const fileNameLabel = document.getElementById('file-name-label');
        if('{{ Auth::user()->trade_license }}'){
            fileNameLabel.textContent = '{{ Auth::user()->trade_license }}';
        }

        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                fileNameLabel.textContent = fileInput.files[0].name;
            } else {
                fileNameLabel.textContent = 'Choose File';
            }
        });
    });
</script>
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
    </script>

    @if (get_setting('google_map') == 1)
        @include('shop.frontend.partials.google_map')
    @endif

@endsection
