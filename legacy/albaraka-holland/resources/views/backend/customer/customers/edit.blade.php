@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="mb-0 h6">{{ translate('Edit Customer') }}</h5>

</div>
<div class="">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form class="form form-horizontal mar-top" action="{{route('customers.update', $customer->id)}}" method="POST" enctype="multipart/form-data" id="choice_form">
        <div class="row gutters-5">
            <div class="col-lg-8">
                <input name="_method" type="hidden" value="POST">
                <input type="hidden" name="id" value="{{ $customer->id }}">
                @csrf
                <div class="card">
                    <div class="card-body m-4">
                        <div class="form-group row">
                            <label class="col-lg-3 col-from-label">{{translate('Customer Name')}} </label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" name="name" placeholder="{{translate('Customer Name')}}" value="{{ $customer->name }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="email" class="col-lg-3 col-from-label">{{  translate('Email') }}</label>
                            <div class="col-lg-8">
                                <input type="email" class="form-control" value="{{ $customer->email }}" placeholder="{{  translate('Email') }}" name="email" readonly>
                            </div>
                            @if ($errors->has('email'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                        </div>
                        <div class="form-group row">
                            <label for="phone" class="col-lg-3 col-from-label">{{  translate('phone') }}</label>
                            <div class="col-lg-8">
                                <input type="tel" class="form-control" value="{{ $customer->phone }}" placeholder="{{  translate('phone') }}" name="phone">
                            </div>
                            @if ($errors->has('phone'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('phone') }}</strong>
                                </span>
                            @endif
                        </div>
                        <div class="form-group row">
                            <label for="country" class="col-lg-3 col-from-label">{{  translate('Country') }}</label>
                            <div class="col-lg-8">
                                <select class="form-control" name="country">
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->name }}" {{ $customer->country === $country->name ? 'selected' : '' }}>{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if ($errors->has('country'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('country') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- Tax Number -->
                        <div class="form-group row">
                            <label for="tax_number" class="col-lg-3 col-from-label">{{  translate('Tax Number') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" value="{{ $customer->tax_number }}" placeholder="{{  translate('Tax Number') }}" name="tax_number">
                            </div>
                            @if ($errors->has('tax_number'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('tax_number') }}</strong>
                                </span>
                            @endif
                        </div>
                        <!-- Company Address -->
                        <div class="form-group row">
                            <label for="company_address" class="col-lg-3 col-from-label">{{  translate('Company Address') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" value="{{ $customer->company_address }}" placeholder="{{  translate('Company Address') }}" name="company_address">
                            </div>
                            @if ($errors->has('company_address'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('company_address') }}</strong>
                                </span>
                            @endif
                        </div>
                        <!-- Company Name -->
                        <div class="form-group row">
                            <label for="company_name" class="col-lg-3 col-from-label">{{  translate('Company Name') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" value="{{ $customer->company_name }}" placeholder="{{  translate('Company Name') }}" name="company_name">
                            </div>
                            @if ($errors->has('company_name'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('company_name') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- Company Shipping Address -->
                        <div class="form-group row">
                            <label for="shipping_address" class="col-lg-3 col-from-label">{{  translate('Shipping Address') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" value="{{ $customer->shipping_address }}" placeholder="{{  translate('Shipping Address') }}" name="shipping_address">
                            </div>
                            @if ($errors->has('shipping_address'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('shipping_address') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- Bank Account Number -->
                        <div class="form-group row">
                            <label for="bank_account_number" class="col-lg-3 col-from-label">{{  translate('Bank Account Number') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" value="{{ $customer->bank_account_number }}" placeholder="{{  translate('Bank Account Number') }}" name="bank_account_number">
                            </div>
                            @if ($errors->has('bank_account_number'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('bank_account_number') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- Member Serial -->
                        <div class="form-group row">
                            <label for="member_serial" class="col-lg-3 col-from-label">{{  translate('Member Serial') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" value="{{ $customer->member_serial }}" placeholder="{{  translate('Member Serial') }}" name="member_serial" disabled>
                            </div>
                            @if ($errors->has('member_serial'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('member_serial') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- AccSysID -->
                        <div class="form-group row">
                            <label for="AccSysID" class="col-lg-3 col-from-label">{{  translate('Otajer ID') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" value="{{ $customer->AccSysID }}" placeholder="{{  translate('Otajer ID') }}" name="AccSysID" disabled>
                            </div>
                            @if ($errors->has('AccSysID'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('AccSysID') }}</strong>
                                </span>
                            @endif
                        </div>


                        <div class="form-group row">
                            <label class="col-lg-3 col-from-label" for="is_rep">{{ translate('Is Representative') }}</label>
                            <div class="col-lg-8">
                                <label class="aiz-switch aiz-switch-success mb-0">
                                    <input type="checkbox" id="is_rep" name="is_rep" {{ $customer->is_rep ? 'checked' : '' }}>
                                    <span></span>
                                    @if ($errors->has('is_rep'))
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $errors->first('is_rep') }}</strong>
                                        </span>
                                    @endif
                                </label>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="trade_license" class="col-lg-3 col-from-label">{{  translate('Trade License') }}</label>
                            <div class="col-lg-8">
                                <input type="file" class="form-control" value="{{ $customer->trade_license }}" placeholder="{{  translate('Upload Trade License') }}" name="trade_license">
                                @isset($customer->trade_license)
                                <a class="btn btn-info my-2" href="{{ route('download.trade_license', ['filename' => $customer->trade_license])}}"> Download License </a>
                                @endisset
                            </div>
                            @if ($errors->has('trade_license'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('trade_license') }}</strong>
                                </span>
                            @endif
                        </div>
                        <div class="card rounded-0 shadow-none border">
                            <div class="card-header pt-4 border-bottom-0">
                                <h5 class="mb-0 fs-18 fw-700 text-dark">{{ translate('Shipping Address')}}</h5>
                            </div>
                            <div class="card-body">
                                @foreach ($customer->addresses as $key => $address)
                                    <div class="">
                                        <div class="border p-4 mb-4 position-relative">
                                            <div class="row fs-14 mb-2 mb-md-0">
                                                <span class="col-md-2 text-secondary">{{ translate('Shipping Address') }}:</span>
                                                <span class="col-md-8 text-dark">{{ $address->address }}</span>
                                            </div>
                                            <div class="row fs-14 mb-2 mb-md-0">
                                                <span class="col-md-2 text-secondary">{{ translate('City') }}:</span>
                                                <span class="col-md-10 text-dark">{{ optional($address->city)->name }}</span>
                                            </div>
                                            <div class="row fs-14 mb-2 mb-md-0">
                                                <span class="col-md-2 text-secondary">{{ translate('State') }}:</span>
                                                <span class="col-md-10 text-dark">{{ optional($address->state)->name }}</span>
                                            </div>
                                            <div class="row fs-14 mb-2 mb-md-0">
                                                <span class="col-md-2 text-secondary">{{ translate('Country') }}:</span>
                                                <span class="col-md-10 text-dark">{{ optional($address->country)->name }}</span>
                                            </div>
                                            <div class="row fs-14 mb-2 mb-md-0">
                                                <span class="col-md-2 text-secondary text-secondary">{{ translate('Phone') }}:</span>
                                                <span class="col-md-10 text-dark">{{ $address->phone }}</span>
                                            </div>
                                            <div class="row fs-14 mb-2 mb-md-0">
                                                <span class="col-md-2 text-secondary text-secondary">{{ translate('Postal Code') }}:</span>
                                                <span class="col-md-10 text-dark">{{ $address->postal_code }}</span>
                                            </div>
                                            {{-- @if ($address->set_default)
                                                <div class="absolute-md-top-right pt-2 pt-md-4 pr-md-5">
                                                    <span class="badge badge-inline badge-warning text-white p-3 fs-12" style="border-radius: 25px; min-width: 80px !important;">{{ translate('Default') }}</span>
                                                </div>
                                            @endif --}}
                                            <div class="dropdown position-absolute right-0 top-0 pt-4 mr-1">
                                                <button class="btn bg-gray text-white px-1 py-1" type="button" data-toggle="dropdown">
                                                    <i class="la la-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                                                    <a class="dropdown-item" onclick="edit_address('{{$address->id}}')">
                                                        {{ translate('Edit') }}
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('addresses.destroy', $address->id) }}">{{ translate('Delete') }}</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                <!-- Add New Address -->
                                <div class="" onclick="add_new_address()">
                                    <div class="border p-3 mb-3 c-pointer text-center bg-light has-transition hov-bg-soft-light">
                                        <i class="la la-plus la-2x"></i>
                                        <div class="alpha-7 fs-14 fw-700">{{ translate('Add New Address') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <div class="col-12">
                <div class="mb-3 text-right">
                    @if(! $customer->registration_completed)
                    <a href="{{ route("customers.sendAgreement", $customer->id) }}" class="mx-4 btn btn-danger">Send Agreements Email</a>
                    @endif
                    <button type="submit" name="button" class="mx-4 btn btn-primary">{{ translate('Update Customer') }}</button>
                </div>
            </div>
        </div>
    </form>
</div>

@section('modal')
    <!-- Address modal -->
    @include('backend.customer.customers.address_modal')
@endsection

@endsection
