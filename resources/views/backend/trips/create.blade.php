@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{translate('Create Trip')}}</h5>
</div>

<div class="col-lg mx-auto">
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
            <h5 class="mb-0 h6">{{translate('Trip')}}</h5>
        </div>

        <div class="card-body">
            <form action="{{ route('trips.store') }}" method="POST">
                <input name="_method" type="hidden" value="POST">
                @csrf
                <div class="form-group row">
                    <label class="col-md-1 control-label required" for="start_date">{{ translate('Due Date') }} <span class="text-danger">*</span></label>
                    <div class="col-md">
                        <input type="date" class="form-control aiz-date" name="due_date" id="due_date" placeholder="{{ translate('Select Date') }}"
                            value="{{ old('due_date') }}" data-time-picker="true" autocomplete="off" required>
                            <!-- Hint for invalid date -->
                            <div id="due-date-hint" class="text-danger mt-1" style="display:none;">
                                {{ translate('No drivers or trucks are available for the selected date. Please choose a different date.') }}
                            </div>
                    </div>
                </div>

                <div class="form-group row" id="driver">
                    <label class="col-sm-1 col-from-label">{{ translate('Driver') }}</label>
                    <div class="col-sm">
                        <select class="form-control aiz-selectpicker" name="driver_id" id="driver_id" data-live-search="true">
                            <option value="">{{ translate('Select Driver') }}</option>
                            <!-- Drivers will be populated here based on the chosen date -->
                        </select>
                        <!-- Hint for empty driver list -->
                        <div id="driver-hint" class="text-danger mt-1" style="display:none;">
                            {{ translate('No drivers available for the selected date.') }}
                        </div>
                    </div>
                </div>

                <div class="form-group row" id="truck">
                    <label class="col-sm-1 col-from-label required">{{ translate('Truck') }} <span class="text-danger">*</span></label>
                    <div class="col-sm">
                        <select class="form-control aiz-selectpicker" name="truck_id" id="truck_id" data-live-search="true" required>
                            <option value="">{{ translate('Select Truck') }}</option>
                            <!-- Trucks will be populated here based on the chosen date -->
                        </select>
                        <!-- Hint for empty truck list -->
                        <div id="truck-hint" class="text-danger mt-1" style="display:none;">
                            {{ translate('No trucks available for the selected date.') }}
                        </div>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-1 col-from-label" for="notes">{{ translate('Notes')}}</label>
                    <div class="col-sm">
                        <textarea type="text" placeholder="{{ translate('Notes')}}" name="notes" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>

                {{-- <div class="form-group row">
                    <label class="col-sm-1 col-from-label required">{{ translate('Auto Sort') }}</label>
                    <div class="col-sm">
                        <label class="aiz-switch aiz-switch-success mb-0">
                            <input type="checkbox" name="sort_by_shortest" value="1">
                            <span></span>
                        </label>
                        <span class="mt-1">
                            {{ translate('Enable this option to automatically sort based on the shortest path.') }}
                        </span>
                    </div>
                </div> --}}

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 h6">{{ translate('Checkpoints') }}</h5>
                    </div>
                    <div class="card-body">
                        <div id="checkpoints" class="sortable-checkpoints">
                            <!-- Template form -->
                            <!-- if the user want to add new checkpoint then this template will be used -->
                            @include('backend.trips.components.checkpoint-template')

                            @if (old('checkpoints_array'))
                                @foreach (old('checkpoints_array') as $key => $checkpoint)
                                    @include('backend.trips.components.checkpoint', [
                                        'type' => $checkpoint['checkpoint_type'],
                                        'checkpoint' => null,
                                        'key' => $key
                                    ])
                                @endforeach
                            @endif
                        </div>
                        <button type="button" class="btn btn-primary action-btn" id="add-order-checkpoint">{{ translate('Add Order Checkpoint') }}</button>
                        <button type="button" class="mx-3 btn btn-primary action-btn" id="add-customer-checkpoint">{{ translate('Add Customer Checkpoint') }}</button>
                    </div>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-primary" onclick="(deleteTemplatesCheckpoints())">{{translate('Save')}}</button>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div id="map" style="height: 30rem; width: auto; border:0; margin: auto;"></div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

@endsection

@section('style')
    @include('backend.trips.inc.styles')
@endsection

@section('script')
    @include('backend.trips.inc.scripts')
    @include('backend.trips.inc.map-scripts')
@endsection
