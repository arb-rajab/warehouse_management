@extends('backend.layouts.app')

@section('content')
@php
        use Carbon\Carbon;

    @endphp
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ translate('All Visits') }}</h1>
        </div>
    </div>


    <div class="card">
        <form class="" id="sort_visits" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('Visits') }}</h5>
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
                <div class="col-lg-2">
                    <div class="form-group mb-0">
                        <input type="text" class="aiz-date-range form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" data-format="DD-MM-Y" data-separator=" to " data-advanced-range="true" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <input type="text" class="form-control" id="search"
                            name="search" @if(request()->has('search')) value="{{ request()->search }}" @endisset
                            placeholder="{{ translate('Type rep or customer name & Enter') }}">
                    </div>
                </div>
                <div class="col-auto">
                    <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
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
                            <th>{{ translate('Rep Name') }}</th>
                            <th>{{ translate('Customer Name') }}</th>
                            <th>{{ translate('Note') }}</th>
                            <th>{{ translate('Visit Date') }}</th>
                            <th>{{ translate('Has Order') }}</th>
                            <th>{{ translate('Options') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visits as $key => $visit)
                            @if ($visit != null)
                                <tr>
                                    <!--<td>{{ $key + 1 + ($visits->currentPage() - 1) * $visits->perPage() }}</td>-->
                                    <td>
                                        <div class="form-group">
                                            <div class="aiz-checkbox-inline">
                                                <label class="aiz-checkbox">
                                                    <input type="checkbox" class="check-one" name="id[]"
                                                        value="{{ $visit->id }}">
                                                    <span class="aiz-square-check"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </td>

                                    <td>{{ $visit->rep?->name }}</td>
                                    <td>{{ $visit->customer?->name }}</td>
                                    <td>{{ $visit->note }}</td>
                                    <td>{{ \Carbon\Carbon::parse($visit->visit_date)->format('F j, Y, g:i a') }}</td>
                                    <td>
                                        @if($visit->order)
                                            <span class="badge badge-inline badge-success">{{ translate('True') }}</span>
                                        @else
                                            <span class="badge badge-inline badge-danger">{{ translate('False') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="dropdown mb-2 mb-md-0 ">
                                            <button class="btn btn-sm border dropdown-toggle" type="button"
                                                data-toggle="dropdown">
                                                {{ translate('Actions') }}
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-right">
                                                {{-- @if ($visit->order) --}}
                                                    <a href="{{route('admin.rep.visits.show', $visit->id)}}" class="dropdown-item" data-href="{{route('admin.rep.visits.show', $visit->id)}}" title="{{ translate('Show the visit to this client') }}">
                                                        {{ translate('View Visit Details') }}
                                                    </a>
                                                {{-- @endif --}}

                                                <a href="{{route('admin.rep.visits.rep-show', ['id' => $visit->rep->id, 'date'=> \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d')])}}" class="dropdown-item" data-href="{{route('admin.rep.visits.rep-show', $visit->rep->id)}}" title="{{ translate('Show Represetative Visits') }}">
                                                    {{ translate('View All Representative Visits on this Day') }}
                                                </a>

                                                <a href="#" class="dropdown-item confirm-delete"
                                                    data-href="{{ route('admin.rep.visits.destroy', $visit->id) }}"
                                                    title="{{ translate('Delete') }}">
                                                    {{ translate('Delete') }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                    {{ $visits->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>

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

        function bulk_delete() {
            var data = new FormData($('#sort_visits')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('bulk-visit-delete')}}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function (response) {
                    if(response == 1) {
                        location.reload();
                    }
                }
            });
        }
    </script>
@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection
