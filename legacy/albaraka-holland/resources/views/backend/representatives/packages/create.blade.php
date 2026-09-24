@extends('backend.layouts.app')

@section('content')

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
                <h5 class="mb-0 h6">{{translate('Create New Package')}}</h5>
            </div>

            <form class="form-horizontal" action="{{ route('rep.packages.store') }}" method="POST" enctype="multipart/form-data">
            	@csrf
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-sm-2 col-from-label" for="name">{{translate('Package Name')}}</label>
                        <div class="col-sm-10">
                            <input type="text" placeholder="{{translate('Name')}}" id="name" name="name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-2 col-from-label" for="percentage">{{translate('Percentage')}}</label>
                        <div class="col-sm-10">
                            <input type="number" min="0" max="100" step="0.01" placeholder="{{translate('Percentage')}}" id="percentage" name="percentage" class="form-control" required>
                        </div>
                    </div>


                    <div class="form-group mb-0 text-right">
                        <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                    </div>

                </div>
            </form>
        </div>
    </div>

@endsection
