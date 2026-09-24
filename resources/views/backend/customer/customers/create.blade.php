@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="mb-0 h6">{{ translate('Create Customer') }}</h5>

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
    <form class="form form-horizontal mar-top" action="{{route('customers.store')}}" method="POST" enctype="multipart/form-data" id="choice_form">
        <div class="row gutters-5">
            <div class="col-lg-8">
                <input name="_method" type="hidden" value="POST">
                @csrf

                <div class="card">
                    <div class="card-body m-4">
                        <div class="form-group row">
                            <label class="col-lg-3 col-from-label">{{translate('Customer Name')}} </label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="{{translate('Customer Name')}}">
                            </div>
                            @if ($errors->has('name'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('name') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- Company Name -->
                        <div class="form-group row">
                            <label for="company_name" class="col-lg-3 col-from-label">{{  translate('Company Name') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" placeholder="{{  translate('Company Name') }}" name="company_name" value="{{ old('company_name') }}">
                            </div>
                            @if ($errors->has('company_name'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('company_name') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- Email -->
                        <div class="form-group row">
                            <label for="email" class="col-lg-3 col-from-label">{{  translate('Email') }}</label>
                            <div class="col-lg-8">
                                <input type="email" class="form-control" placeholder="{{  translate('Email') }}" name="email" value="{{ old('email') }}">
                            </div>
                            @if ($errors->has('email'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                        </div>
                        <!-- password -->
                        <div class="form-group row">
                            <label for="password" class="col-lg-3 col-from-label">{{  translate('password') }}</label>
                            <div class="col-lg-8">
                                <input type="password" class="form-control" placeholder="{{  translate('password') }}" name="password">
                            </div>
                            @if ($errors->has('password'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- password_confirmation -->
                        <div class="form-group row">
                            <label for="password_confirmation" class="col-lg-3 col-from-label">{{  translate('Confirm Password') }}</label>
                            <div class="col-lg-8">
                                <input type="password" class="form-control" placeholder="{{  translate('Confirm Password') }}" name="password_confirmation">
                            </div>
                            @if ($errors->has('password_confirmation'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('password_confirmation') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- Phone -->
                        <div class="form-group row">
                            <label for="phone" class="col-lg-3 col-from-label">{{  translate('phone') }}</label>
                            <div class="col-lg-8">
                                <input type="tel" class="form-control" placeholder="{{  translate('phone') }}" name="phone" value="{{ old('phone') }}">
                            </div>
                            @if ($errors->has('phone'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('phone') }}</strong>
                                </span>
                            @endif
                        </div>

                        <!-- Country -->
                        <div class="form-group row">
                            <label for="country" class="col-lg-3 col-from-label">{{  translate('Country') }}</label>
                            <div class="col-lg-8">
                                <select class="form-control" name="country">
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->name }}" {{ old('country') == $country->name ? 'selected' : '' }}>{{ $country->name }}</option>
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
                                <input type="text" class="form-control" placeholder="{{  translate('Tax Number') }}" name="tax_number" value="{{ old('tax_number') }}">
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
                                <input type="text" class="form-control" placeholder="{{  translate('Company Address') }}" name="company_address" value="{{ old('company_address') }}">
                            </div>
                            @if ($errors->has('company_address'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('company_address') }}</strong>
                                </span>
                            @endif
                        </div>


                        <!-- Company Shipping Address -->
                        <div class="form-group row">
                            <label for="shipping_address" class="col-lg-3 col-from-label">{{  translate('Shipping Address') }}</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control" placeholder="{{  translate('Shipping Address') }}" name="shipping_address" value="{{ old('shipping_address') }}">
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
                                <input type="text" class="form-control" placeholder="{{  translate('Bank Account Number') }}" name="bank_account_number" value="{{ old('bank_account_number') }}">
                            </div>
                            @if ($errors->has('bank_account_number'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('bank_account_number') }}</strong>
                                </span>
                            @endif
                        </div>


                        <div class="form-group row">
                            <label class="col-lg-3 col-from-label" for="user_type">{{ translate('User Type') }}</label>
                            <div class="col-lg-8">
                                <select class="form-control" name="user_type">
                                        <option value="customer" selected>{{ translate('Customer') }}</option>
                                        <option value="admin">{{ translate('Admin') }}</option>
                                        <option value="accountant">{{ translate('Accountant') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="trade_license" class="col-lg-3 col-from-label">{{  translate('Trade License') }}</label>
                            <div class="col-lg-8">
                                <input type="file" class="form-control" placeholder="{{  translate('Upload Trade License') }}" name="trade_license">
                            </div>
                            @if ($errors->has('trade_license'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('trade_license') }}</strong>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            <div class="col-12">
                <div class="mb-3 text-right">
                    <button type="submit" name="button" class="mx-4 btn btn-primary">{{ translate('Store Customer') }}</button>
                </div>
            </div>
        </div>
    </form>
</div>

@endsection
