@extends('backend.layouts.app')

@section('content')
    @php
        // CoreComponentRepository::instantiateShopRepository();
        // CoreComponentRepository::initializeCache();
    @endphp

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{ translate('Notification Information') }}</h5>
                </div>
                <div class="card-body">
                    <form class="form-horizontal" action="{{ route('admin.notifications.send') }}" method="POST">
                        @csrf
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">{{ translate('Title') }}</label>
                            <div class="col-md-9">
                                <input type="text" placeholder="{{ translate('Title') }}" id="title" name="title"
                                    class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">{{ translate('Description') }}</label>
                            <div class="col-md-9">
                                <input type="text" placeholder="{{ translate('Description') }}" id="body"
                                    name="body" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">{{ translate('Customer') }}</label>
                            <div class="col-md-9">
                                <select class="select2 form-control aiz-selectpicker" name="user_id" data-toggle="select2"
                                    data-placeholder="Choose ..."data-live-search="true">
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">
                                            {{ $customer->name . ' ' . $customer->AccSysID }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
