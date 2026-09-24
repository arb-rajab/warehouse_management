@extends('backend.layouts.app')

@section('content')

@php
    use Carbon\Carbon;

@endphp
<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
        <h1 class="h3">{{translate('All Customers')}}</h1>
    </div>
</div>


<div class="card">
    <form class="" id="sort_customers" action="" method="GET">
        <div class="card-header row gutters-5">
            <div class="col">
                <h5 class="mb-0 h6">{{translate('Customers')}}</h5>
            </div>

            <div class="dropdown mb-2 mb-md-0">
                <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                    {{translate('Bulk Action')}}
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" onclick="bulk_delete()">{{translate('Delete selection')}}</a>
                </div>
            </div>

            <div class="dropdown mb-2 mb-md-0 ml-1">
                <button type="button" class="btn border menu-dropdown dropdown-toggle" data-toggle="dropdown">
                    {{ translate('Filter') }}
                </button>
                <div class="dropdown-menu dropdown-menu-right dropdown-menu-sub-dropdown" onclick="stopPropagation(event)">
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
                                    href="{{ route('customers.index', ['allCustomers' => true]) }}"
                                    title="{{ translate('All Customers') }}">{{ translate('All Customers') }}
                                </a>

                                <div class="separator border-gray-200 py-2"></div>

                                <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                    href="{{ route('customers.index', ['allActive' => true]) }}"
                                    title="{{ translate('All Active') }}">{{ translate('All Active') }}
                                </a>
                                <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                    href="{{ route('customers.index', ['allFromApi' => true]) }}"
                                    title="{{ translate('All From Api') }}">{{ translate('All From Api') }}
                                </a>
                                <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                    href="{{ route('customers.index', ['new_users' => true]) }}"
                                    title="{{ translate('New Users') }}">{{ translate('New Users') }}
                                </a>
                                <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                    href="{{ route('customers.index', ['not_important' => true]) }}"
                                    title="{{ translate('Not Important customers') }}">{{ translate('Not Important customers') }}
                                </a>
                                <a class="btn btn-soft-success btn-circle btn-sm mb-2"
                                    href="{{ route('customers.index', ['allRegistrationCompleted' => true]) }}"
                                    title="{{ translate('All Registration Completed') }}">{{ translate('All Registration Completed') }}
                                </a>
                            </div>

                            <div class="separator border-gray-200 py-2"></div>

                            <div class="py-1" >
                            <label class="aiz-switch aiz-switch-success px-2 " style="font-size:15px;" for="active">{{ translate('active') }}
                                <input type="checkbox" name="active" id="active" class="mr-2 check-one filter" @if (request()->has('active')) checked @endif>
                                <span class="slider round"></span>
                            </label>
                            </div>
                            <div class="py-1">
                            <label class="aiz-switch aiz-switch-success  px-2 " style="font-size:15px;" for="from_api">{{ translate('From API') }}
                                <input type="checkbox" name="from_api" id="from_api" class="mr-2 check-one filter" @if (request()->has('from_api')) checked @endif>
                                <span class="slider round"></span>
                            </label>
                            </div>

                            <div class="py-1" >
                            <label class="aiz-switch aiz-switch-success  px-2 " style="font-size:15px;" for="registration_completed">{{ translate('Registration Completed') }}
                                <input type="checkbox"  name="registration_completed" id="registration_completed" class="mr-2 check-one filter" @if (request()->has('registration_completed')) checked @endif>
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
                    <input type="text" class="form-control" id="search" name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type email or name & Enter') }}">
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
                        <th>{{translate('Otajer ID')}}</th>
                        <th>{{translate('Name')}}</th>
                        <th>{{translate('Company Name')}}</th>
                        <th data-breakpoints="xl">{{translate('Email Address')}}</th>
                        <th >{{translate('Phone')}}</th>
                        <th >{{translate('Country')}}</th>
                        <th >{{translate('Company Address')}}</th>
                        <th >{{translate('Tax Number')}}</th>
                        <th >{{translate('Shipping Address')}}</th>
                        <th data-breakpoints="xl">{{translate('Bank Account Number')}}</th>
                        <th data-breakpoints="xl">{{translate('Trade License')}}</th>
                        <th data-breakpoints="xl">{{translate('Member Serial')}}</th>
                        <th data-breakpoints="xl">{{translate('Registration Completed?')}}</th>
                        <th data-breakpoints="xl">{{translate('From api')}}</th>
                        <th data-breakpoints="xl">{{translate('User activity status')}}</th>
                        <th data-breakpoints="xl">{{translate('Admin Notes')}}</th>
                        <th data-breakpoints="xl">{{translate('Synched With Otajer')}}</th>
                        <th >{{ translate('Profits') }}</th>
                        <th >{{ translate('Status') }}</th>
                        <th >{{ translate('Active Until') }}</th>

                        @if(get_setting('wallet_system'))
                        <th data-breakpoints="xl">{{translate('Package')}}</th>
                        <th data-breakpoints="xl">{{translate('Wallet Balance')}}</th>
                        @endif
                        <th>{{translate('Options')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $key => $user)
                        @if ($user != null)
                            <tr>
                                <!--<td>{{ ($key+1) + ($users->currentPage() - 1)*$users->perPage() }}</td>-->
                                <td>
                                    <div class="form-group">
                                        <div class="aiz-checkbox-inline">
                                            <label class="aiz-checkbox">
                                                <input type="checkbox" class="check-one" name="id[]" value="{{$user->id}}">
                                                <span class="aiz-square-check"></span>
                                            </label>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @isset($user->AccSysID){{ $user->AccSysID }}
                                    @endisset
                                </td>
                                <td>@if($user->banned == 1) <i class="fa fa-ban text-danger" aria-hidden="true"></i> @endif {{$user->name}}</td>
                                <td>{{$user->company_name}}</td>
                                <td>{{$user->email}}</td>
                                <td>{{$user->phone}}</td>
                                <td>{{$user->country}}</td>
                                <td>{{$user->company_address}}</td>
                                <td>{{$user->tax_number}}</td>
                                <td>{{$user->shipping_address}}</td>
                                <td>{{$user->bank_account_number}}</td>

                                {{-- Trade License --}}
                                @isset($user->trade_license)
                                <td>
                                    <a href="{{ route('download.trade_license', ['filename' => $user->trade_license])}}"> Download </a>
                                </td>
                                @else
                                <td>
                                    No License
                                </td>
                                @endisset
                                {{-- End: Trade License --}}

                                {{-- member_serial --}}
                                @isset($user->member_serial)
                                <td>
                                    {{ $user->member_serial }}
                                </td>
                                @else
                                <td>
                                    -
                                </td>
                                @endisset
                                {{-- End: member_serial --}}

                                {{-- Registration Completed ? --}}
                                <td>
                                    <span class="badge badge-inline badge-{{ $user->registration_completed ? 'success' : 'danger'  }} ">{{  $user->registration_completed ? translate('Completed') : translate('Not Completed')  }}</span>
                                </td>

                                {{-- From API --}}
                                <td>
                                    <span class="badge badge-inline badge-{{ $user->from_api ? 'success' : 'danger'  }} ">{{ $user->from_api ? translate('True') : translate('False')  }}</span>
                                </td>

                                {{-- End: From API --}}

                                {{-- User Activity Status --}}
                                <td>
                                    <span class="badge badge-inline badge-{{ $user->activity_status != 'new_user' ? 'success' : 'warning'  }} ">{{ $user->activity_status == 'new_user' ? translate('New User') : translate('Has Been Contacted')  }}</span>
                                </td>
                                {{-- End:User Activity Status --}}

                                {{-- Notes --}}
                                <td>
                                    <p>{{ $user->admin_notes }}</p>

                                    <a href="#" onclick="openUpdateModal(this)" data-customer-id="{{ $user->id }}" data-old-notes="{{ $user->admin_notes }}" class="btn btn-sm btn-soft-warning btn-icon btn-circle btn-sm" title="{{ translate('Update Customer Notes') }}">
                                        <i class="las la-edit"></i>
                                    </a>
                                </td>
                                {{-- End:Notes --}}

                                {{-- Synched With Otajer --}}
                                <td>
                                    <span class="badge badge-inline badge-{{ $user->synched_with_otajer ? 'success' : 'danger'  }} ">{{ $user->synched_with_otajer ? translate('True') : translate('False')  }}</span>
                                </td>
                                {{-- End: Synched With Otajer --}}

                                {{-- Customer Profits --}}
                                <td>
                                    {{ single_price($user->profits) }}
                                </td>

                                {{-- End: Customer Profits --}}

                                {{-- Status --}}
                                @if($user->admin_verified)
                                <td>{{ translate('Active') }}</td>
                                @else
                                <td>{{ translate('Not Active') }}</td>
                                @endif
                                {{-- End Status --}}


                                {{-- Active until --}}
                                @if($user->admin_verified)
                                @php
                                    $lastOrderDate = Carbon::parse($user->last_order_at);
                                    $lastOrderDate = $lastOrderDate->addDays(30);
                                    $lastOrderDate = $lastOrderDate->format('Y-m-d');

                                    $admin_verified_til =  Carbon::parse($user->admin_verified_at);
                                    $admin_verified_til = $admin_verified_til->addDays(30);
                                    $admin_verified_til = $admin_verified_til->format('Y-m-d');
                                @endphp
                                @if($user->last_order_at != null)
                                <td>
                                    {{  $lastOrderDate  }}
                                </td>
                                @elseif($user->admin_verified_at != null)
                                <td>
                                    {{ $admin_verified_til }}
                                </td>
                                @endif

                                @else
                                <td>
                                   Not Active
                                </td>
                                @endif
                                {{-- End: Active untill --}}





                                @if ($user->customer_package != null)
                                <td>
                                    {{$user->customer_package->getTranslation('name')}}
                                </td>
                                @endif
                                @if(get_setting('wallet_system') == 1)
                                <td>{{single_price($user->balance)}}</td>
                                @endif
                                <td >
                                    <div class="dropdown mb-2 mb-md-0 ">
                                        <button class="btn btn-sm border dropdown-toggle" type="button" data-toggle="dropdown">
                                            {{translate('Actions')}}
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-right">
                                            @if($user->admin_verified != 1)
                                                <a href="{{ route('customers.verify', encrypt($user->id)) }}" class="dropdown-item" title="{{ translate('Verify Customer') }}">
                                                    {{ translate('Verify Customer') }}
                                                </a>
                                            @else
                                                <a href="{{ route('customers.verify', encrypt($user->id)) }}" class="dropdown-item"  title="{{ translate('UnVerify Customer') }}">
                                                    {{ translate('UnVerify Customer') }}
                                                </a>
                                            @endif

                                            @can('login_as_customer')
                                                <a href="{{route('customers.login', encrypt($user->id))}}" class="dropdown-item" title="{{ translate('Log in as this Customer') }}">
                                                    {{ translate('Log in as this Customer') }}
                                                </a>
                                            @endcan

                                            @can('ban_customer')
                                                @if($user->banned != 1)
                                                    <a href="#" class="dropdown-item" onclick="confirm_ban('{{route('customers.ban', encrypt($user->id))}}');" title="{{ translate('Ban this Customer') }}">
                                                        {{ translate('Ban this Customer') }}
                                                    </a>
                                                    @else
                                                    <a href="#" class=" dropdown-item" onclick="confirm_unban('{{route('customers.ban', encrypt($user->id))}}');" title="{{ translate('Unban this Customer') }}">
                                                        {{ translate('Unban this Customer') }}
                                                    </a>
                                                @endif
                                            @endcan

                                            @can('view_all_customers')
                                                <a href="{{ route('customers.page-views', $user->id) }}" class="dropdown-item">
                                                    <span class="aiz-side-nav-text">{{ translate('Customer Pages Views') }}</span>
                                                </a>
                                            @endcan

                                            <a href="{{route('customers.edit', $user->id)}}" class="dropdown-item" data-href="{{route('customers.edit', $user->id)}}" title="{{ translate('Edit') }}">
                                                {{ translate('Edit') }}
                                            </a>

                                            <a href="{{route('change_password', $user->id)}}" class="dropdown-item" data-href="{{route('change_password', $user->id)}}" title="{{ translate('Change Password') }}">
                                                {{ translate('Change Password') }}
                                            </a>

                                            <a href="{{route('customers.whatsapp_contact', $user->id)}}" target="_blank"
                                                class="dropdown-item" data-href="{{route('customers.whatsapp_contact', $user->id)}}"
                                                title="{{ translate('Customer Whatsapp') }}">
                                                {{ translate('Customer Whatsapp') }}
                                            </a>

                                            @if (!$user->is_important)
                                                <a href="{{route('customers.important', $user->id)}}"
                                                    class="dropdown-item" data-href="{{route('customers.important', $user->id)}}"
                                                    title="{{ translate('Important') }}">
                                                    {{ translate('Important') . '?' }}
                                                </a>
                                            @else
                                                <a href="{{route('customers.not_important', $user->id)}}"
                                                    class="dropdown-item" data-href="{{route('customers.not_important', $user->id)}}"
                                                    title="{{ translate('Not important') }}">
                                                    {{ translate('Not important') . '?' }}
                                                </a>
                                            @endif

                                            @if ($user->activity_status == 'has_been_contacted')
                                                <a href="{{route('customers.change_activity_status', ['user_id' => $user->id, 'new_status' => 'new_user'])}}"
                                                    class="dropdown-item" data-href="{{route('customers.change_activity_status', ['user_id' => $user->id, 'new_status' => 'has_been_contacted'])}}"
                                                    title="{{ translate('New User') }}">
                                                    {{ translate('New User') . '?' }}
                                                </a>
                                                @else
                                                <a href="{{route('customers.change_activity_status', ['user_id' => $user->id, 'new_status' => 'has_been_contacted'])}}"
                                                    class="dropdown-item" data-href="{{route('customers.change_activity_status', ['user_id' => $user->id, 'new_status' => 'has_been_contacted'])}}"
                                                    title="{{ translate('Has been contacted') }}">
                                                    {{ translate('Has been contacted') . '?' }}
                                                </a>
                                            @endif

                                            @can('delete_customer')
                                                <a href="#" class="dropdown-item confirm-delete" data-href="{{route('customers.destroy', $user->id)}}" title="{{ translate('Delete') }}">
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
                {{ $users->appends(request()->input())->links() }}
            </div>
        </div>
    </form>
</div>


<!-- update notes Modal -->
<div id="update-modal" class="modal fade">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title h6">{{translate('Update Customer Notes')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="customer-id">
                <textarea id="update-notes" class="form-control" rows="4" value=""></textarea>
                <button type="button" class="btn btn-secondary rounded-0 mt-2" data-dismiss="modal">{{translate('Cancel')}}</button>
                <button type="button" class="btn btn-primary rounded-0 mt-2" onclick="update_notes()">{{translate('Update')}}</button>
            </div>
        </div>
    </div>
</div><!-- /.modal -->


<div class="modal fade" id="confirm-ban">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title h6">{{translate('Confirmation')}}</h5>
                <button type="button" class="close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>{{translate('Do you really want to ban this Customer?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
                <a type="button" id="confirmation" class="btn btn-primary">{{translate('Proceed!')}}</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirm-unban">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title h6">{{translate('Confirmation')}}</h5>
                <button type="button" class="close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>{{translate('Do you really want to unban this Customer?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
                <a type="button" id="confirmationunban" class="btn btn-primary">{{translate('Proceed!')}}</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection

@section('script')
    <script type="text/javascript">

        $(document).on("change", ".check-all", function() {
            if(this.checked) {
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

        function sort_customers(el){
            $('#sort_customers').submit();
        }
        function confirm_ban(url)
        {
            $('#confirm-ban').modal('show', {backdrop: 'static'});
            document.getElementById('confirmation').setAttribute('href' , url);
        }

        function confirm_unban(url)
        {
            $('#confirm-unban').modal('show', {backdrop: 'static'});
            document.getElementById('confirmationunban').setAttribute('href' , url);
        }

        function bulk_delete() {
            var data = new FormData($('#sort_customers')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('bulk-customer-delete')}}",
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

        function openUpdateModal(button) {
            var customerId = button.getAttribute('data-customer-id');
            var oldNotes = button.getAttribute('data-old-notes');
            document.getElementById('update-notes').value = oldNotes;
            document.getElementById('customer-id').value = customerId;
            $('#update-modal').modal('show');

        }

        function update_notes(id){
            var updatedNotes = document.getElementById('update-notes').value;
            var customerId = document.getElementById('customer-id').value;
            $('#update-modal').modal('hide');

            $.post('{{ route('customers.update_customer_notes') }}', {_token:'{{ csrf_token() }}', id:customerId, admin_notes:updatedNotes }, function(data){
                if(data == 1){
                    AIZ.plugins.notify('success', '{{ translate('customer Notes updated successfully') }}');
                    var updateButton = document.querySelector('[data-customer-id="' + customerId + '"]');
                    updateButton.setAttribute('data-old-notes', updatedNotes);
                }
                else{
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }

    </script>
@endsection
