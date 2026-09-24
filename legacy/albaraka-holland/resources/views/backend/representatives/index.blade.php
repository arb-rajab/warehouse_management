@extends('backend.layouts.app')

@section('content')
    @php
        use Carbon\Carbon;

    @endphp
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ translate('All Representatives') }}</h1>
        </div>
    </div>


    <div class="card">
        <form class="" id="sort_customers" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('Representatives') }}</h5>
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
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Rep ID') }}</th>
                            <th>{{ translate('Rep Serial') }}</th>
                            <th>{{ translate('Email Address') }}</th>
                            <th>{{ translate('Phone') }}</th>
                            <th >{{ translate('Profits') }}</th>
                            <th>{{ translate('Discount Percentage') }}</th>
                            <th>{{ translate('Options') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($representatives as $key => $user)
                            @if ($user != null)
                                <tr>
                                    <!--<td>{{ $key + 1 + ($representatives->currentPage() - 1) * $representatives->perPage() }}</td>-->
                                    <td>
                                        <div class="form-group">
                                            <div class="aiz-checkbox-inline">
                                                <label class="aiz-checkbox">
                                                    <input type="checkbox" class="check-one" name="id[]"
                                                        value="{{ $user->id }}">
                                                    <span class="aiz-square-check"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($user->banned == 1)
                                            <i class="fa fa-ban text-danger" aria-hidden="true"></i>
                                        @endif {{ $user->name }}
                                    </td>
                                    <td>{{ $user->rep_id }}</td>
                                    <td>{{ $user->rep_serial }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->phone }}</td>

                                    <td>
                                        {{ single_price($user->profits) }}
                                    </td>

                                    {{-- Discount rep Package --}}
                                    @if ($user->representativePackage)
                                        <td class="text-danger font-weight-bold"> {{ $user->representativePackage->name . "  [%". $user->representativePackage->percentage ."]" }}</td>
                                    @else
                                        <td>{{ translate('No Package!') }}</td>
                                    @endif
                                    {{-- End Package --}}


                                    @if ($user->customer_package != null)
                                        <td>
                                            {{ $user->customer_package->getTranslation('name') }}
                                        </td>
                                    @endif
                                    @if (get_setting('wallet_system') == 1)
                                        <td>{{ single_price($user->balance) }}</td>
                                    @endif
                                    <td>
                                        <div class="dropdown mb-2 mb-md-0 ">
                                            <button class="btn btn-sm border dropdown-toggle" type="button"
                                                data-toggle="dropdown">
                                                {{ translate('Actions') }}
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-right">


                                                @can('login_as_customer')
                                                    <a href="{{ route('customers.login', encrypt($user->id)) }}"
                                                        class="dropdown-item"
                                                        title="{{ translate('Log in as this Representative') }}">
                                                        {{ translate('Log in as this Representative') }}
                                                    </a>
                                                @endcan

                                                @can('view_all_customers')
                                                    <a href="{{ route('customers.page-views', $user->id) }}" class="dropdown-item">
                                                        <span class="aiz-side-nav-text">{{ translate('Representative Pages Views') }}</span>
                                                    </a>
                                                @endcan

                                                <a  href="#" onclick="show_packages_modal('{{$user->id}}');"  class="dropdown-item">
                                                    {{ translate('Change Package') }}
                                                </a>

                                                @can('ban_customer')
                                                    @if ($user->banned != 1)
                                                        <a href="#" class="dropdown-item"
                                                            onclick="confirm_ban('{{ route('customers.ban', encrypt($user->id)) }}');"
                                                            title="{{ translate('Ban this Representative') }}">
                                                            {{ translate('Ban this Representative') }}
                                                        </a>
                                                    @else
                                                        <a href="#" class=" dropdown-item"
                                                            onclick="confirm_unban('{{ route('customers.ban', encrypt($user->id)) }}');"
                                                            title="{{ translate('Unban this Representative') }}">
                                                            {{ translate('Unban this Representative') }}
                                                        </a>
                                                    @endif
                                                @endcan
                                                <a href="{{route('customers.edit', $user->id)}}" class="dropdown-item" data-href="{{route('customers.edit', $user->id)}}" title="{{ translate('Edit') }}">
                                                    {{ translate('Edit') }}
                                                </a>

                                                <a href="{{route('change_password', $user->id)}}" class="dropdown-item" data-href="{{route('change_password', $user->id)}}" title="{{ translate('Change Password') }}">
                                                    {{ translate('Change Password') }}
                                                </a>

                                                @can('delete_customer')
                                                    <a href="{{route('customer.destroy', $user->id)}}" class="dropdown-item confirm-delete"
                                                        data-href="{{ route('customers.destroy', $user->id) }}"
                                                        title="{{ translate('Delete') }}">
                                                        {{ translate('Delete') }}
                                                    </a>
                                                @endcan
                                            </div>

                                        </div>

                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                    {{ $representatives->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>


    <div class="modal fade" id="confirm-ban">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h6">{{ translate('Confirmation') }}</h5>
                    <button type="button" class="close" data-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>{{ translate('Do you really want to ban this Customer?') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <a type="button" id="confirmation" class="btn btn-primary">{{ translate('Proceed!') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirm-unban">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h6">{{ translate('Confirmation') }}</h5>
                    <button type="button" class="close" data-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>{{ translate('Do you really want to unban this Customer?') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <a type="button" id="confirmationunban" class="btn btn-primary">{{ translate('Proceed!') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    @include('modals.delete_modal')


     <!-- Package Modal -->
     <div class="modal fade" id="package_modal">
		<div class="modal-dialog">
			<div class="modal-content" id="package-modal-content">

			</div>
		</div>
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

        function sort_customers(el) {
            $('#sort_customers').submit();
        }

        function show_packages_modal(id){
            $.post('{{ route('rep.packages.modal') }}',{_token:'{{ @csrf_token() }}', id:id}, function(data){
                $('#package_modal #package-modal-content').html(data);
                $('#package_modal').modal('show', {backdrop: 'static'});
            });
        }

        function confirm_ban(url) {
            $('#confirm-ban').modal('show', {
                backdrop: 'static'
            });
            document.getElementById('confirmation').setAttribute('href', url);
        }

        function confirm_unban(url) {
            $('#confirm-unban').modal('show', {
                backdrop: 'static'
            });
            document.getElementById('confirmationunban').setAttribute('href', url);
        }

        function bulk_delete() {
            var data = new FormData($('#sort_customers')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ route('bulk-customer-delete') }}",
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
    </script>
@endsection
