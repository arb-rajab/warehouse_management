@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar mt-2 mb-3">
    <h5 class="mb-0 h6">{{translate('Update Package Information')}}</h5>
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
        <div class="card-body p-0">
            <ul class="nav nav-tabs nav-fill border-light">

            </ul>
            <form class="p-4" action="{{ route('rep.packages.update') }}" method="POST">

            	@csrf
                <input type="hidden" name="id" value="{{ $package->id }}">
                <div class="form-group row">
                    <label class="col-sm-2 col-from-label" for="name">{{translate('Package Name')}}</label>
                    <div class="col-sm-10">
                        <input type="text"  value="{{ $package->name }}" id="name" name="name" class="form-control" required>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-2 col-from-label" for="percentage">{{translate('percentage')}}</label>
                    <div class="col-sm-10">
                        <input type="number" min="0" max="100" step="0.01" placeholder="{{translate('percentage')}}" value="{{ $package->percentage }}" id="percentage" name="percentage" class="form-control" required>
                    </div>
                </div>

                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
