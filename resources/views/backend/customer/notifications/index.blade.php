@extends('backend.layouts.app')

@section('content')
    @php
        // CoreComponentRepository::instantiateShopRepository();
        // CoreComponentRepository::initializeCache();
    @endphp

    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h3">{{ translate('All Notifications') }}</h1>
            </div>
            @can('add_notification')
                <div class="col-md-6 text-md-right">
                    <a href="{{ route('admin.notifications.create') }}" class="btn btn-primary">
                        <span>{{ translate('Add New Notification') }}</span>
                    </a>
                </div>
            @endcan
        </div>
    </div>
    <div class="card">
        <div class="card-header d-block d-md-flex">
            {{-- <h5 class="mb-0 h6">{{ translate('Notifications') }}</h5>
            <form class="" id="sort_categories" action="" method="GET">
                <div class="box-inline pad-rgt pull-left">
                    <div class="" style="min-width: 200px;">
                        <input type="text" class="form-control" id="search"
                            name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                            placeholder="{{ translate('Type name & Enter') }}">
                    </div>
                </div>
            </form> --}}
        </div>
        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <th data-breakpoints="lg">#</th>
                        <th data-breakpoints="lg">{{ translate('title') }}</th>
                        <th data-breakpoints="lg">{{ translate('status') }}</th>
                        <th data-breakpoints="lg">{{ translate('customer') }}</th>
                        <th width="10%" class="text-right">{{ translate('Options') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notifications as $key => $notification)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $notification->title }}</td>
                            <td>{{ $notification->status }}</td>
                            <td>{{ $notification?->user?->name }}</td>

                            <td class="text-right">
                                @can('delete_notification')
                                    <a href="{{ route('admin.notifications.delete', ['id' => $notification->id]) }}"
                                        class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                                        title="{{ translate('Delete') }}">
                                        <i class="las la-trash"></i>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $notifications->appends(request()->input())->links() }}
            </div>
        </div>
    </div>
@endsection

{{--
@section('modal')
    @include('modals.delete_modal')
@endsection


@section('script')
    <script type="text/javascript">
        function update_featured(el) {
            if (el.checked) {
                var status = 1;
            } else {
                var status = 0;
            }
            $.post('{{ route('categories.featured') }}', {
                _token: '{{ csrf_token() }}',
                id: el.value,
                status: status
            }, function(data) {
                if (data == 1) {
                    AIZ.plugins.notify('success', '{{ translate('Featured categories updated successfully') }}');
                } else {
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }
    </script>
@endsection --}}
