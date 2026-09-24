@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{translate('Edit Truck')}}</h5>
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
            <h5 class="mb-0 h6">{{translate('Truck')}}</h5>
        </div>

        <div class="card-body">
          <form action="{{ route('trucks.update') }}" method="POST">
                <input name="_method" type="hidden" value="POST">
                <input name="id" type="hidden" value="{{ $truck->id }}">
                @csrf
                <div class="form-group row">
                    <label class="col-sm-3 col-from-label" for="name">{{translate('Name')}}</label>
                    <div class="col-sm-9">
                        <input type="text" placeholder="{{translate('Name')}}" id="name" name="name" class="form-control" value="{{$truck->name}}" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-sm-3 col-from-label" for="name">{{translate('Model')}}</label>
                    <div class="col-sm-9">
                        <input type="text" placeholder="{{translate('Model')}}" id="model" name="model" class="form-control" value="{{$truck->model}}" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-sm-3 col-from-label" for="name">{{translate('Max Pallets Number')}}</label>
                    <div class="col-sm-9">
                        <input type="text" placeholder="{{translate('Max Pallets Number')}}" id="max_pallets_number" name="max_pallets_number" class="form-control" value="{{$truck->max_pallets_number}}">
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-sm-3 col-from-label" for="license_plate">{{translate('License Plate')}}</label>
                    <div class="col-sm-9">
                        <input type="text" placeholder="{{translate('License Plate')}}" name="license_plate" class="form-control" value="{{$truck->license_plate}}">
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-sm-3 col-from-label" for="description">{{translate('Description')}}</label>
                    <div class="col-sm-9">
                        <textarea type="text" placeholder="{{translate('Description')}}" name="description" class="form-control">{{$truck->description}}</textarea>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-sm-3 col-form-label" for="signinSrEmail">{{translate('Photos')}}</label>
                    <div class="col-sm-9">
                        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                            <input type="hidden" name="photos" value="{{ $truck->photos }}" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-primary">{{translate('Save')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
