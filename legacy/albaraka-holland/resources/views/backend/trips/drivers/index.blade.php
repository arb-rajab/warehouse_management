@extends('backend.layouts.app')

@section('content')
    @php
        use Carbon\Carbon;

    @endphp
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ translate('All Drivers') }}</h1>
        </div>
    </div>


    <div class="card">
        <form class="" id="sort_drivers" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('Drivers') }}</h5>
                </div>

                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <input type="text" class="form-control" id="search"
                            name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                            placeholder="{{ translate('Type email or name & Enter') }}">
                    </div>
                </div>
            </div>

            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <!--<th data-breakpoints="lg">#</th>-->
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('ID') }}</th>
                            <th>{{ translate('Serial') }}</th>
                            <th>{{ translate('Email Address') }}</th>
                            <th>{{ translate('Phone') }}</th>
                            {{-- <th>{{ translate('On Trip') }}</th> --}}
                            <th>{{ translate('Options') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($drivers as $key => $driver)
                            <tr>
                                <td>{{ $driver->name }}</td>
                                <td>{{ $driver->id }}</td>
                                <td>{{ $driver->serial }}</td>
                                <td>{{ $driver->email }}</td>
                                <td>{{ $driver->phone }}</td>
                                {{-- <td>{{ $driver->activeTrip()?->where('status', 'started')->exists() ? "true" : "false" }}</td> --}}

                                <td>
                                    <div class="dropdown mb-2 mb-md-0 ">
                                        <button class="btn btn-sm border dropdown-toggle" type="button"
                                            data-toggle="dropdown">
                                            {{ translate('Actions') }}
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-right">

                                            <a href="{{route('drivers.edit', $driver->id)}}" class="dropdown-item" data-href="{{route('drivers.edit', $driver->id)}}" title="{{ translate('Edit') }}">
                                                {{ translate('Edit') }}
                                            </a>

                                            @can('delete_customer')
                                                <a href="#" class="dropdown-item confirm-delete"
                                                    data-href="{{ route('drivers.destroy', $driver->id) }}"
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
                    {{ $drivers->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>


@endsection

@section('modal')
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

        function sort_drivers(el) {
            $('#sort_drivers').submit();
        }
    </script>
@endsection
