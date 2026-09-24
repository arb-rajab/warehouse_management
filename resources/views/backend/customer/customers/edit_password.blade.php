@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="mb-0 h6">{{ translate('Change Password') }}</h5>

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
    <form class="form form-horizontal mar-top" action="{{route('update_password')}}" method="POST" enctype="multipart/form-data" id="choice_form">
        <div class="row gutters-5">
            <div class="col-lg-4 mx-auto">
                <input name="_method" type="hidden" value="POST">
                <input type="hidden" name="id" value="{{ $customer->id }}">
                @csrf
                <div class="card">
                    <div class="card-header  m-4">
                        <div class="form-group row">
                            <label class="col-lg-6 col-from-label">{{translate('Name')}} </label>
                            <div  class="col-lg-6 text-success">
                               {{ $customer->name }}
                            </div>
                        </div>
                    </div>
                    <div class="card-body m-4">
                        <div class="form-group row">
                            <label class="col-lg-4 col-from-label">{{translate('Enter Password')}} </label>
                            <div class="col-lg-8">
                                <input class="form-control" type="password" name="password" minlength="6" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-lg-4 col-from-label">{{translate('Re-Enter Password')}} </label>
                            <div class="col-lg-8">
                                <input class="form-control" type="password" name="password_confirmation" minlength="6" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-lg-4 mx-auto">
                                <input class="btn btn-primary" type="submit" value="Update Password">
                            </div>
                        </div>
                    </div>
                </div>

        </div>
    </form>
</div>

@endsection
