@extends('backend.layouts.app')

@section('content')

    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h5 class="mb-0 h6">{{ translate('Edit Driver') }}</h5>
    </div>

    <div class="col-lg-6 mx-auto">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{ translate('Driver') }}</h5>
            </div>

            <div class="card-body">
                <form action="{{ route('drivers.update') }}" method="POST">
                    <input name="_method" type="hidden" value="POST">
                    <input name="id" type="hidden" value="{{ $driver->id }}">
                    @csrf
                    <div class="form-group">
                        <label for="name"
                            class="fs-12 fw-700 text-soft-dark required">{{ translate('Full Name') }}</label>
                        <input type="text" class="form-control rounded-0{{ $errors->has('name') ? ' is-invalid' : '' }}"
                            value="{{ old('name', $driver->name) }}" placeholder="{{ translate('Full Name') }}"
                            name="name" required>
                        @if ($errors->has('name'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('name') }}</strong>
                            </span>
                        @endif
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email" class="fs-12 fw-700 text-soft-dark required">{{ translate('Email') }}</label>
                        <input type="email"
                            class="form-control rounded-0{{ $errors->has('email') ? ' is-invalid' : '' }}"
                            value="{{ $driver->email }}" placeholder="{{ translate('Email') }}" name="email" readonly>
                        @if ($errors->has('email'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('email') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="phone" class="fs-12 fw-700 text-soft-dark required">{{ translate('phone') }}</label>
                        <input type="tel"
                            class="form-control rounded-0{{ $errors->has('phone') ? ' is-invalid' : '' }}"
                            value="{{ old('phone', $driver->phone) }}" placeholder="{{ translate('phone') }}"
                            name="phone" required>
                        @if ($errors->has('phone'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('phone') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="country"
                            class="fs-12 fw-700 text-soft-dark required">{{ translate('Country') }}</label>
                        <select class="form-control aiz-selectpicker" name="country" required>
                            @foreach ($countries as $country)
                                <option value="{{ $country->name }}" @if ($country->name == old('country', $driver->country)) selected @endif>
                                    {{ $country->name }}</option>
                            @endforeach
                        </select>

                        @if ($errors->has('country'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('country') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="password" class="fs-12 fw-700 text-soft-dark">{{ translate('Password') }}</label>
                        <input type="password"
                            class="form-control rounded-0{{ $errors->has('password') ? ' is-invalid' : '' }}"
                            placeholder="{{ translate('Password') }}" name="password">
                        @if ($errors->has('password'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('password') }}</strong>
                            </span>
                        @endif
                    </div>

                    <!-- Recaptcha -->
                    @if (get_setting('google_recaptcha') == 1)
                        <div class="form-group">
                            <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_KEY') }}">
                            </div>
                        </div>
                    @endif

                    <div class="form-group mb-0 text-right">
                        <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
