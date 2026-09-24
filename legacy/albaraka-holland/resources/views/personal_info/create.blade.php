@extends('frontend.layouts.app')


@section("content")

<div class="container">
    <div class="row mt-3">
    <div class="col-12">
<form id="reg-form" class="form-default" role="form" action="{{ route('store.personalInfo') }}" method="POST">
    @csrf

    <!-- Name -->
    <div class="form-group col-lg-4">
        <label for="name" class="fs-12 fw-700 text-soft-dark">{{  translate('Full Name') }}</label>
        <input type="text" class="form-control rounded-0" @if($info) value="{{ $info->name  }}" @else value="{{ auth()->user()->name }}" @endif placeholder="{{  translate('Full Name') }}" name="name">
        @error('name')
            <h3>name</h3>
        @enderror
    </div>


    <div class="form-group col-lg-4">
        <label for="email" class="fs-12 fw-700 text-soft-dark">{{  translate('email') }}</label>
        <input type="email" class="form-control rounded-0"  @if($info) value="{{ $info->email  }}" @else value="{{ auth()->user()->email }}" @endif placeholder="{{  translate('Email') }}" name="email">
        @error('email')
        <h3>email</h3>
    @enderror
    </div>


    <div class="form-group col-lg-4">
        <label for="phone" class="fs-12 fw-700 text-soft-dark">{{  translate('Phone Number') }}</label>
        <input type="text" class="form-control rounded-0" @if($info) value="{{ $info->phone  }}" @else value="{{ auth()->user()->phone }}" @endif placeholder="{{  translate('phone') }}" name="phone">

        @error('phone')
        <h3>phone</h3>
    @enderror
    </div>


    <div class="form-group col-lg-4">
        <label for="country" class="fs-12 fw-700 text-soft-dark">{{  translate('Country') }}</label>
        <input type="country" class="form-control rounded-0" @if($info) value="{{ $info->country  }}" @else value="{{ auth()->user()->country }}" @endif placeholder="{{  translate('Country') }}" name="country">

    </div>

    <div class="form-group col-lg-4">
        <label for="city" class="fs-12 fw-700 text-soft-dark">{{  translate('City') }}</label>
        <input type="city" class="form-control rounded-0" @if($info) value="{{ $info->city  }}" @else value="{{ auth()->user()->city }}" @endif placeholder="{{  translate('City') }}" name="city">
    </div>


    <!-- Submit Button -->
    <div class="mb-4 mt-4 col-lg-4">
        <button type="submit" class="btn btn-primary btn-block fw-600 rounded-4">{{  translate('Submit') }}</button>
    </div>


</form>
    </div>
    </div>
</div>

@endsection
