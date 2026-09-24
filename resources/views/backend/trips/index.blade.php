@extends('backend.layouts.app')

@section('content')
    @php
        use Carbon\Carbon;

    @endphp
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ $page_title }}</h1>
        </div>
    </div>


    <div class="card">
          
        <form class="" id="sort_trips" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('Trips') }}</h5>
                </div>
                  <div class="col-auto">
                    <div class="form-group mb-0">
                        <a href="{{ route('new.feat-order.trip.create') }}"
                            class="btn btn-primary">{{ translate('Create Order') }}</a>
                    </div>
                </div>
                  <div class="col-auto">
                    <div class="form-group mb-0">
                        <a href="{{ route('trips.create') }}"
                            class="btn btn-primary">{{ translate('Create Trip') }}</a>
                    </div>
                </div>

                <div class="dropdown mb-2 mb-md-0">
                    <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                        {{ translate('Bulk Action') }}
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="#"
                            onclick="bulk_delete()">{{ translate('Delete selection') }}</a>
                    </div>
                </div>
              

                <div class="dropdown mb-2 mb-md-0 ml-1">
                    <button type="button" class="btn border menu-dropdown dropdown-toggle" data-toggle="dropdown">
                        {{ translate('Filter') }}
                    </button>
                    <div class="dropdown-menu dropdown-menu-right dropdown-menu-sub-dropdown"
                        onclick="stopPropagation(event)">
                        <div class="px-3 py-2">
                            <div class="d-flex align-items-center">
                                <span class="fs-5 text-dark fw-bold mr-2">{{ translate('Filter Options') }}</span>
                            </div>
                        </div>

                        <div class="separator border-gray-200"></div>

                        <div class="px-3 py-2">
                            <div class="mb-10 py-2">

                                <div class="py-1">
                                    <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                        href="{{ url()->current() . '?allTrips=true' }}"
                                        title="{{ translate('All trips') }}">{{ translate('All trips') }}
                                    </a>
                                </div>

                                <div class="separator border-gray-200 py-2"></div>

                                <div class="py-1">
                                    <label class="aiz-switch aiz-switch-success px-2 " style="font-size:15px;"
                                        for="pending">{{ translate('Pending Trips') }}
                                        <input type="checkbox" name="pending" id="pending" class="mr-2 check-one filter"
                                            @if (request()->has('pending')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="py-1">
                                    <label class="aiz-switch aiz-switch-success  px-2 " style="font-size:15px;"
                                        for="started">{{ translate('Started Trips') }}
                                        <input type="checkbox" name="started" id="started" class="mr-2 check-one filter"
                                            @if (request()->has('started')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div class="py-1">
                                    <label class="aiz-switch aiz-switch-success  px-2 " style="font-size:15px;"
                                        for="closed">{{ translate('Closed Trips') }}
                                        <input type="checkbox" name="closed" id="closed" class="mr-2 check-one filter"
                                            @if (request()->has('closed')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">{{ translate('Apply') }}</button>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <input type="text" class="form-control" id="search"
                            name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                            placeholder="{{ translate('Type trip code & Enter') }}">
                    </div>
                </div>
            </div>

            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <!--<th data-breakpoints="lg">#</th>-->
                            <th>
                                <div class="form-group">
                                    <div class="aiz-checkbox-inline">
                                        <label class="aiz-checkbox">
                                            <input type="checkbox" class="check-all">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </div>
                                </div>
                            </th>
                            <th>{{ translate('Code') }}</th>
                            <th>{{ translate('Driver Name') }}</th>
                            <th>{{ translate('Truck') }}</th>
                            <th>{{ translate('Due Date') }}</th>
                            {{-- <th>{{ translate('Is Active') }}</th> --}}
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Started At') }}</th>
                            <th>{{ translate('Closed At') }}</th>
                            <th>{{ translate('Distance') }}</th>
                            <th>{{ translate('Duration') }}</th>
                            <th>{{ translate('Notes') }}</th>
                            <th>{{ translate('Options') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trips as $key => $trip)
                            <tr>
                                <td>
                                    <div class="form-group">
                                        <div class="aiz-checkbox-inline">
                                            <label class="aiz-checkbox">
                                                <input type="checkbox" class="check-one" name="id[]"
                                                    value="{{ $trip->id }}">
                                                <span class="aiz-square-check"></span>
                                            </label>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <a href="{{ route('trips.show', $trip->id) }}">
                                        {{ $trip->code }}
                                    </a>
                                </td>

                                <td>{{ $trip->driver->name ?? 'N/A' }}</td>
                                @if ($trip->truck)
                                    <td>{{ '#' . $trip->truck->id . ' ' . $trip->truck->name }}</td>
                                @else
                                    <td>N/A</td>
                                @endif

                                <td>
                                    @if ($trip->due_date)
                                        <span class="badge badge-inline badge-light">
                                            {{ Carbon::parse($trip->due_date)->format('Y-m-d') }}
                                        </span>
                                    @endif
                                </td>

                                {{-- <td>
                                    @if ($trip->active)
                                        <span class="badge badge-inline badge-success">
                                            {{ $trip->active }}
                                        </span>
                                    @endif
                                </td> --}}

                                <td>
                                    <span id="trip-status-{{ $trip->id }}">{{ translate($trip->status) }}</span>
                                    <a href="#" onclick="openUpdateModal(this)" data-trip-id="{{ $trip->id }}"
                                        data-old-status="{{ $trip->status }}"
                                        class="btn btn-soft-warning btn-icon btn-circle btn-sm"
                                        title="{{ translate('Update Status') }}">
                                        <i class="las la-edit"></i>
                                    </a>
                                </td>

                                <td>
                                    @if ($trip->started_at)
                                        <span class="badge badge-inline badge-light">
                                            {{ Carbon::parse($trip->started_at)->format('Y-m-d H:i') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($trip->closed_at)
                                        <span class="badge badge-inline badge-light">
                                            {{ Carbon::parse($trip->closed_at)->format('Y-m-d H:i') }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span class="badge badge-inline badge-info">
                                        {{ number_format($trip->distance / 1000, 2) }} km
                                    </span>
                                </td>

                                <td>
                                    <span class="badge badge-inline badge-success">
                                        {{ Carbon::now()->addSeconds($trip->duration)->locale('en')->diffForHumans(null, true, true, 2) ?? 'N/A' }}
                                    </span>
                                </td>

                                <td>
                                    <textarea type="text" class="form-control trip-notes" style="width: 7rem;" data-trip-id="{{ $trip->id }}">{{ $trip->notes }}</textarea>
                                </td>
                                <td>
                                    <div class="dropdown mb-2 mb-md-0 ">
                                        <button class="btn btn-sm border dropdown-toggle" type="button"
                                            data-toggle="dropdown">
                                            {{ translate('Actions') }}
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-right">

                                            <a href="{{ route('trips.edit', $trip->id) }}" class="dropdown-item"
                                                data-href="{{ route('trips.edit', $trip->id) }}"
                                                title="{{ translate('Edit') }}">
                                                {{ translate('Edit') }}
                                            </a>

                                            @can('delete_customer')
                                                <a href="#" class="dropdown-item confirm-delete"
                                                    data-href="{{ route('trips.destroy', $trip->id) }}"
                                                    title="{{ translate('Delete') }}">
                                                    {{ translate('Delete') }}
                                                </a>
                                            @endcan
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                    {{ $trips->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>
@endsection

@section('modal')
    @include('backend.trips.components.update-trip-status-modal')
    @include('modals.delete_modal')
@endsection

@section('script')
    <script type="text/javascript">
        $(document).on("change", ".check-all", function() {
            if (this.checked) {
                // Iterate each checkbox
                $('.check-one:checkbox').each(function() {
                    this.checked = true;
                });
            } else {
                $('.check-one:checkbox').each(function() {
                    this.checked = false;
                });
            }

        });

        function sort_trips(el) {
            $('#sort_trips').submit();
        }

        function stopPropagation(event) {
            event.stopPropagation();
        }

        $(document).ready(function() {

            $('#all_customers').change(function() {
                // Disable or enable other checkboxes based on the "all" checkbox state
                $('.filter').not(this).prop('disabled', this.checked);

                // Remove the 'checked' attribute from other checkboxes
                if (this.checked) {
                    $('.check-one').not(this).prop('checked', false);
                }
            });
        });

        function bulk_delete() {
            var data = new FormData($('#sort_trips')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('trips.bulk-trip-delete') }}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response == 1) {
                        location.reload();
                    }
                }
            });
        }

        $(document).on("change", ".trip-notes", function() {
            var inputField = $(this);
            var tripId = inputField.data("trip-id");
            var newNotes = inputField.val();

            $.ajax({
                url: "{{ route('trips.update-notes') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    trip_id: tripId,
                    notes: newNotes
                },
                success: function(response) {
                    if (response.success) {
                        inputField.css("border", "2px solid green"); // Highlight on success
                        AIZ.plugins.notify('success',
                            "{{ translate('Notes updated successfully') }}");
                    } else {
                        inputField.css("border", "2px solid red"); // Highlight on error
                        AIZ.plugins.notify('danger', "{{ translate('Error updating Notes') }}");
                    }
                },
                error: function() {
                    inputField.css("border", "2px solid red"); // Highlight on error
                    AIZ.plugins.notify('danger', "{{ translate('Something went wrong') }}");
                }
            });
        });
    </script>
@endsection
